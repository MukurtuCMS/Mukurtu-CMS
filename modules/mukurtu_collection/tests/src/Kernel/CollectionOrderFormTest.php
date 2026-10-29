<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_collection\CollectionOrder;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Form\CollectionOrderForm;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the form that sets the order of collections on /collections.
 */
#[CoversClass(CollectionOrderForm::class)]
#[CoversClass(CollectionOrder::class)]
#[Group('mukurtu_collection')]
class CollectionOrderFormTest extends CollectionTestBase {

  use UserCreationTrait;

  /**
   * A node ID that doesn't belong to any collection.
   */
  protected const MISSING_ID = 9999;

  /**
   * Top-level collections, keyed by title.
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection[]
   */
  protected array $collections = [];

  /**
   * A sub-collection of the "Bravo" collection.
   */
  protected Collection $child;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['Charlie', 'alpha', 'Bravo', 'Delta'] as $title) {
      $this->collections[$title] = $this->buildCollection($title);
      $this->collections[$title]->save();
    }

    $this->child = $this->buildCollection('Aardvark');
    $this->child->save();
    $this->collections['Bravo']->addChildCollection($this->child);
    $this->collections['Bravo']->save();

    // A saved order that names a missing node and a sub-collection, and
    // leaves out alpha and Bravo.
    $this->config(CollectionOrder::CONFIG_NAME)
      ->set('sort_mode', CollectionOrder::MODE_CUSTOM)
      ->set('order', [
        (int) $this->collections['Delta']->id(),
        static::MISSING_ID,
        (int) $this->child->id(),
        (int) $this->collections['Charlie']->id(),
      ])
      ->save();
  }

  /**
   * Gets the IDs of the named top-level collections.
   *
   * @param string[] $titles
   *   Collection titles.
   *
   * @return int[]
   *   Node IDs, in the same order.
   */
  protected function ids(array $titles): array {
    return array_map(fn (string $title): int => (int) $this->collections[$title]->id(), $titles);
  }

  /**
   * Gets the row keys of the order table.
   *
   * @param array $form
   *   The built form.
   *
   * @return int[]
   *   Node IDs, in row order.
   */
  protected function rowIds(array $form): array {
    $rows = array_filter($form['custom_order']['collections'], fn ($key): bool => is_int($key), ARRAY_FILTER_USE_KEY);
    uasort($rows, fn (array $a, array $b): int => $a['#weight'] <=> $b['#weight']);
    return array_keys($rows);
  }

  /**
   * Saved collections come first, then the rest alphabetically.
   */
  public function testBuildFormOrdersRows(): void {
    $form = $this->container->get('form_builder')->getForm(CollectionOrderForm::class);

    $this->assertSame(
      $this->ids(['Delta', 'Charlie', 'alpha', 'Bravo']),
      $this->rowIds($form),
    );
    $this->assertSame(CollectionOrder::MODE_CUSTOM, $form['sort_mode']['#default_value']);
    // The table is in a container because #states can't hide a bare table.
    $this->assertSame('container', $form['custom_order']['#type']);
    $this->assertSame(
      ['visible' => [':input[name="sort_mode"]' => ['value' => CollectionOrder::MODE_CUSTOM]]],
      $form['custom_order']['#states'],
    );

    $delta = $form['custom_order']['collections'][$this->collections['Delta']->id()];
    $this->assertSame('Delta', $delta['label']['#plain_text']);
    $this->assertSame(0, $delta['weight']['#default_value']);
    $this->assertSame('Weight for Delta', (string) $delta['weight']['#title']);
  }

  /**
   * Submitting saves the order by weight and drops IDs that don't belong.
   */
  public function testSubmitSavesOrder(): void {
    $weights = [
      'alpha' => 3,
      'Bravo' => 0,
      'Charlie' => 2,
      // Ties keep the row order: Delta was shown before Bravo.
      'Delta' => 0,
    ];
    $values = [
      'sort_mode' => CollectionOrder::MODE_CUSTOM,
      'collections' => [],
      'op' => 'Save configuration',
    ];
    foreach ($weights as $title => $weight) {
      $values['collections'][$this->collections[$title]->id()] = ['weight' => $weight];
    }

    $form_state = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(CollectionOrderForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $order = $this->container->get('mukurtu_collection.collection_order');
    $this->assertSame($this->ids(['Delta', 'Bravo', 'Charlie', 'alpha']), $order->getOrder());
    $this->assertSame(CollectionOrder::MODE_CUSTOM, $order->getSortMode());
  }

  /**
   * Collections the user can't view are left off the form but keep their place.
   */
  public function testHiddenCollectionsKeepTheirPlace(): void {
    // Under a strict protocol the manager below isn't a member of, so they
    // can't view it.
    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $hidden = $this->buildCollection('Secret title');
    $hidden->setProtocols([$strict]);
    $hidden->save();

    // The base class's user is uid 1, which bypasses access checks.
    $manager = $this->createUser(['access content', 'administer collection order']);
    $this->setCurrentUser($manager);
    $this->assertFalse($hidden->access('view', $manager));
    $this->assertTrue($this->collections['Delta']->access('view', $manager));

    $this->config(CollectionOrder::CONFIG_NAME)
      ->set('order', [
        (int) $this->collections['Delta']->id(),
        (int) $hidden->id(),
        (int) $this->collections['Charlie']->id(),
      ])
      ->save();

    $form = $this->container->get('form_builder')->getForm(CollectionOrderForm::class);
    $this->assertSame($this->ids(['Delta', 'Charlie', 'alpha', 'Bravo']), $this->rowIds($form));
    $this->assertStringNotContainsString('Secret title', (string) $this->container->get('renderer')->renderInIsolation($form));

    $values = [
      'sort_mode' => CollectionOrder::MODE_CUSTOM,
      'collections' => [],
      'op' => 'Save configuration',
    ];
    foreach (['Charlie' => 0, 'Delta' => 1, 'alpha' => 2, 'Bravo' => 3] as $title => $weight) {
      $values['collections'][$this->collections[$title]->id()] = ['weight' => $weight];
    }
    $form_state = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(CollectionOrderForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    // The hidden collection stays second; the others fill in around it.
    $this->assertSame(
      [
        (int) $this->collections['Charlie']->id(),
        (int) $hidden->id(),
        (int) $this->collections['Delta']->id(),
        (int) $this->collections['alpha']->id(),
        (int) $this->collections['Bravo']->id(),
      ],
      $this->container->get('mukurtu_collection.collection_order')->getOrder(),
    );
  }

  /**
   * Resetting rebuilds the table alphabetically without saving.
   */
  public function testResetRebuildsAlphabetically(): void {
    $form_builder = $this->container->get('form_builder');
    $values = [
      'sort_mode' => CollectionOrder::MODE_CUSTOM,
      'collections' => [],
      'op' => 'Reset to alphabetical',
    ];
    foreach (['Delta' => 0, 'Charlie' => 1, 'alpha' => 2, 'Bravo' => 3] as $title => $weight) {
      $values['collections'][$this->collections[$title]->id()] = ['weight' => $weight];
    }
    $form_state = (new FormState())->setValues($values);
    $form_builder->submitForm(CollectionOrderForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertTrue($form_state->isRebuilding());

    // Nothing is saved.
    $order = $this->container->get('mukurtu_collection.collection_order');
    $this->assertSame(CollectionOrder::MODE_CUSTOM, $order->getSortMode());
    $this->assertSame(
      [
        (int) $this->collections['Delta']->id(),
        static::MISSING_ID,
        (int) $this->child->id(),
        (int) $this->collections['Charlie']->id(),
      ],
      $order->getOrder(),
    );
    $messages = $this->container->get('messenger')->messagesByType('status');
    $this->assertSame('The table is now in alphabetical order. Save the configuration to keep it.', (string) end($messages));

    // Programmed submits don't rebuild, so rebuild here as a browser POST
    // would. Rebuilding caches the form, which core refuses on GET.
    $form_state->setRequestMethod('POST');
    $form = $form_builder->rebuildForm('mukurtu_collection_order_form', $form_state);
    $this->assertSame($this->ids(['alpha', 'Bravo', 'Charlie', 'Delta']), $this->rowIds($form));
    $this->assertSame(0, $form['custom_order']['collections'][$this->collections['alpha']->id()]['weight']['#default_value']);
  }

  /**
   * Saving after a reset keeps hidden collections in place.
   */
  public function testResetThenSaveKeepsHiddenCollections(): void {
    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $hidden = $this->buildCollection('Secret title');
    $hidden->setProtocols([$strict]);
    $hidden->save();

    $manager = $this->createUser(['access content', 'administer collection order']);
    $this->setCurrentUser($manager);

    $this->config(CollectionOrder::CONFIG_NAME)
      ->set('order', [
        (int) $this->collections['Delta']->id(),
        (int) $hidden->id(),
        (int) $this->collections['Charlie']->id(),
      ])
      ->save();

    $form_builder = $this->container->get('form_builder');
    $form_state = (new FormState())->setValues([
      'sort_mode' => CollectionOrder::MODE_CUSTOM,
      'op' => 'Reset to alphabetical',
    ]);
    $form_builder->submitForm(CollectionOrderForm::class, $form_state);
    $form_state->setRequestMethod('POST');
    $form = $form_builder->rebuildForm('mukurtu_collection_order_form', $form_state);
    $this->assertSame($this->ids(['alpha', 'Bravo', 'Charlie', 'Delta']), $this->rowIds($form));

    // Save the rows as the rebuilt form shows them.
    $values = [
      'sort_mode' => CollectionOrder::MODE_CUSTOM,
      'collections' => [],
      'op' => 'Save configuration',
    ];
    foreach ($this->rowIds($form) as $weight => $nid) {
      $values['collections'][$nid] = ['weight' => $weight];
    }
    $form_state = (new FormState())->setValues($values);
    $form_builder->submitForm(CollectionOrderForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $this->assertSame(
      [
        (int) $this->collections['alpha']->id(),
        (int) $hidden->id(),
        (int) $this->collections['Bravo']->id(),
        (int) $this->collections['Charlie']->id(),
        (int) $this->collections['Delta']->id(),
      ],
      $this->container->get('mukurtu_collection.collection_order')->getOrder(),
    );
  }

  /**
   * Weights follow the sort mode and the saved order.
   */
  public function testWeights(): void {
    $order = $this->container->get('mukurtu_collection.collection_order');

    $this->assertSame(0, $order->getWeight((int) $this->collections['Delta']->id()));
    $this->assertSame(3, $order->getWeight((int) $this->collections['Charlie']->id()));
    $this->assertSame(CollectionOrder::UNPLACED_WEIGHT, $order->getWeight((int) $this->collections['alpha']->id()));

    $order->save(CollectionOrder::MODE_ALPHABETICAL, $order->getOrder());
    $this->assertSame(0, $order->getWeight((int) $this->collections['Charlie']->id()));
    $this->assertSame(0, $order->getWeight((int) $this->collections['alpha']->id()));
  }

  /**
   * Only users with the permission can reach the form.
   */
  public function testRouteAccess(): void {
    $this->container->get('router.builder')->rebuild();
    $access_manager = $this->container->get('access_manager');

    $allowed = $this->createUser(['administer collection order']);
    $denied = $this->createUser(['access content']);

    $this->assertTrue($access_manager->checkNamedRoute('mukurtu_collection.collection_order', [], $allowed));
    $this->assertFalse($access_manager->checkNamedRoute('mukurtu_collection.collection_order', [], $denied));
  }

}
