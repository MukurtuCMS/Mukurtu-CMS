<?php

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Form\CollectionOrganizationForm;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

/**
 * Tests accessibility markup on the Collection Organization form.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1978
 */
class CollectionOrganizationFormAccessibilityTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_collection',
    'geofield',
    'path_alias',
    'mukurtu_local_contexts',
  ];

  /**
   * The root collection.
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected $rootCollection;

  /**
   * A child collection of the root collection.
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected $childCollection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    NodeType::create([
      'type' => 'collection',
      'name' => 'Collection',
    ])->save();

    $community = Community::create(['name' => 'Community']);
    $community->save();
    $community->addMember($this->currentUser);

    $protocol = Protocol::create([
      'name' => 'Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);

    $this->rootCollection = $this->createEmptyCollection('Root Collection');
    $this->rootCollection->setSharingSetting('any');
    $this->rootCollection->setProtocols([$protocol]);
    $this->rootCollection->save();

    $this->childCollection = $this->createEmptyCollection('Child Collection');
    $this->childCollection->setSharingSetting('any');
    $this->childCollection->setProtocols([$protocol]);
    $this->childCollection->save();

    $this->rootCollection->addChildCollection($this->childCollection);
    $this->rootCollection->save();
  }

  /**
   * Creates an empty collection.
   */
  protected function createEmptyCollection(string $title): Collection {
    return Node::create([
      'title' => $title,
      'type' => 'collection',
      'field_items_in_collection' => [],
      'field_keywords' => NULL,
      'field_child_collections' => NULL,
      'field_collection_image' => NULL,
      'field_related_content' => NULL,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
  }

  /**
   * Finds a row in the built collections table by collection ID.
   *
   * $form['collections'] is keyed by position in the organization array
   * (see CollectionOrganizationForm::getOrganizationFromCollection()), not
   * by entity ID, so rows have to be matched via the hidden collection-id
   * value in each row's label column instead.
   */
  protected function findCollectionRow(array $form, int $collection_id): array {
    foreach ($form['collections'] as $row) {
      if (!is_array($row) || !isset($row['label'][2]['#value'])) {
        continue;
      }
      if ((int) $row['label'][2]['#value'] === $collection_id) {
        return $row;
      }
    }
    $this->fail("No collections table row found for collection $collection_id.");
  }

  /**
   * Tests Remove button labeling and the AJAX status/focus scaffolding.
   */
  public function testCollectionsTableAccessibilityMarkup(): void {
    $form = \Drupal::formBuilder()->getForm(CollectionOrganizationForm::class, $this->rootCollection, []);

    // The root collection has no Remove button (line 174), only the child.
    $child_row = $this->findCollectionRow($form, $this->childCollection->id());
    $label = (string) ($child_row['operations']['#attributes']['aria-label'] ?? '');
    $this->assertNotSame('', $label, 'Child row Remove button has an aria-label.');
    $this->assertStringContainsString('Child Collection', $label);

    $root_row = $this->findCollectionRow($form, $this->rootCollection->id());
    $this->assertArrayNotHasKey('aria-label', $root_row['operations']['#attributes'] ?? []);

    // The live region is a sibling of the collections table, not nested
    // inside the AJAX-replaced wrapper, so it survives the wrapper swap.
    $this->assertArrayNotHasKey('collections_status', $form['collections']);
    $this->assertSame('collections-table-status', $form['collections_status']['#attributes']['id']);
    $this->assertSame('polite', $form['collections_status']['#attributes']['aria-live']);
    $this->assertSame('true', $form['collections_status']['#attributes']['aria-atomic']);
    $this->assertContains('visually-hidden', $form['collections_status']['#attributes']['class']);

    // The Add to Collection button has a deterministic id for JS focus
    // targeting.
    $this->assertSame('collections-table-add-button', $form['add']['#attributes']['id']);
  }

  /**
   * Tests that the AJAX callback marks the table as changed.
   */
  public function testCollectionsTableAjaxMarker(): void {
    $form_object = CollectionOrganizationForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$this->rootCollection, []]);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $response = $form_object->addCollectionToTableCallback($form, $form_state);
    $commands = (new \ReflectionProperty($response, 'commands'))->getValue($response);

    $rendered = NULL;
    foreach ($commands as $command) {
      if (($command['selector'] ?? NULL) === '#collections-table') {
        $rendered = (string) $command['data'];
      }
    }
    $this->assertIsString($rendered);
    $this->assertStringContainsString('data-collections-just-changed', $rendered);
  }

}
