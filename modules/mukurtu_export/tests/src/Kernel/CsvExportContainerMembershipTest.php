<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Collections column on item exports.
 *
 * The column is the item's computed field_in_collection. It is exported so
 * that a re-import adds the item back to the same collections.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2798
 */
#[Group('mukurtu_export')]
class CsvExportContainerMembershipTest extends CsvExportFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
    'mukurtu_collection',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
  ];

  /**
   * The collections the item is in.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $collections = [];

  /**
   * The exported item, a person.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');

    // Person is one of the bundles collections accept; page isn't.
    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();

    $item = $this->createNode('person', 'Item');
    foreach (['Collection A', 'Collection B'] as $title) {
      $collection = $this->createNode('collection', $title, ['field_items_in_collection' => [['target_id' => $item->id()]]]);
      $this->collections[] = $collection;
    }
    $this->node = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($item->id());
  }

  /**
   * Collections are exported as IDs, and never pulled into the export.
   */
  public function testExportsIdsOnly(): void {
    // Even when node references are set to export the referenced content.
    $this->export_config->setEntityReferenceSetting('node', 'entity');
    $this->export_config->save();

    $event = new EntityFieldExportEvent('csv', $this->node, 'field_in_collection', $this->context);
    $this->fieldExporter->exportField($event);

    $this->assertEquals(array_map(fn (NodeInterface $c) => $c->id(), $this->collections), $event->getValue());
    $this->assertEmpty($event->context['results']['entities'] ?? [], 'A collection was added to the export.');
  }

  /**
   * With the UUID setting, collections are exported as UUIDs.
   */
  public function testExportsUuids(): void {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->save();

    $event = new EntityFieldExportEvent('csv', $this->node, 'field_in_collection', $this->context);
    $this->fieldExporter->exportField($event);

    $this->assertSame(array_map(fn (NodeInterface $c) => $c->uuid(), $this->collections), $event->getValue());
  }

  /**
   * New export settings offer the column, ticked, only where it applies.
   */
  public function testOfferedForEligibleBundles(): void {
    $exporter = CsvExporter::create(['id' => 'new_settings', 'label' => 'New']);
    $columns = static fn (string $bundle): array => array_column($exporter->getMappedFields('node', $bundle), 'export', 'field_name');

    $this->assertTrue($columns('person')['field_in_collection'] ?? NULL);
    $this->assertArrayNotHasKey('field_in_collection', $columns('page'));
    $this->assertArrayNotHasKey('field_in_collection', $columns('collection'));
  }

  /**
   * Creates and saves a node in the test protocol.
   */
  protected function createNode(string $bundle, string $title, array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => $bundle,
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

}
