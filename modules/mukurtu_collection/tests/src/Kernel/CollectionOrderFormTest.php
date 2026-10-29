<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_collection\CollectionOrder;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Form\CollectionOrderForm;
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
    $rows = array_filter($form['collections'], fn ($key): bool => is_int($key), ARRAY_FILTER_USE_KEY);
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

    $delta = $form['collections'][$this->collections['Delta']->id()];
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
