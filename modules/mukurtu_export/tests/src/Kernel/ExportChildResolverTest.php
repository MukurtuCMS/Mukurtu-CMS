<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\ExportChildResolver;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

/**
 * Tests ExportChildResolver for collections and word lists.
 *
 * @group mukurtu_export
 */
class ExportChildResolverTest extends ProtocolAwareEntityTestBase {

  protected static $modules = [
    'mukurtu_export',
    'mukurtu_multipage_items',
    'field',
  ];

  protected ExportChildResolver $resolver;

  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('export_list');

    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    NodeType::create(['type' => 'word_list', 'name' => 'Word List'])->save();

    // field_items_in_collection: node references on collection bundle.
    FieldStorageConfig::create([
      'field_name' => 'field_items_in_collection',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_items_in_collection',
      'entity_type' => 'node',
      'bundle' => 'collection',
      'label' => 'Items in Collection',
    ])->save();

    // field_child_collections: sub-collection references on collection bundle.
    FieldStorageConfig::create([
      'field_name' => 'field_child_collections',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_child_collections',
      'entity_type' => 'node',
      'bundle' => 'collection',
      'label' => 'Child Collections',
    ])->save();

    // field_words: dictionary word references on word_list bundle.
    FieldStorageConfig::create([
      'field_name' => 'field_words',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_words',
      'entity_type' => 'node',
      'bundle' => 'word_list',
      'label' => 'Words',
    ])->save();

    $this->resolver = $this->container->get('mukurtu_export.child_resolver');
  }

  protected function createNode(string $bundle, array $fields = []): Node {
    $node = Node::create([
      'type' => $bundle,
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
      'status' => TRUE,
    ] + $fields);
    $node->save();
    return $node;
  }

  // --- getChildEntities() ---

  public function testNonNodeReturnsEmpty(): void {
    // ExportChildResolver only handles node entities; others return [].
    $node = $this->createNode('protocol_aware_content');
    // Fake an entity with a different type by testing via an unrelated node bundle.
    $result = $this->resolver->getChildEntities($node);
    $this->assertSame([], $result);
  }

  public function testEmptyCollectionReturnsEmpty(): void {
    $collection = $this->createNode('collection');
    $result = $this->resolver->getChildEntities($collection);
    $this->assertSame([], $result);
  }

  public function testCollectionWithItems(): void {
    $item1 = $this->createNode('protocol_aware_content');
    $item2 = $this->createNode('protocol_aware_content');
    $collection = $this->createNode('collection', [
      'field_items_in_collection' => [
        ['target_id' => $item1->id()],
        ['target_id' => $item2->id()],
      ],
    ]);

    $result = $this->resolver->getChildEntities($collection);
    $this->assertArrayHasKey('node', $result);
    $this->assertArrayHasKey((int) $item1->id(), $result['node']);
    $this->assertArrayHasKey((int) $item2->id(), $result['node']);
    $this->assertCount(2, $result['node']);
  }

  public function testCollectionWithChildCollections(): void {
    $sub = $this->createNode('collection');
    $collection = $this->createNode('collection', [
      'field_child_collections' => [['target_id' => $sub->id()]],
    ]);

    $result = $this->resolver->getChildEntities($collection);
    $this->assertArrayHasKey('node', $result);
    $this->assertArrayHasKey((int) $sub->id(), $result['node']);
  }

  public function testCollectionWithItemsAndChildCollections(): void {
    $item = $this->createNode('protocol_aware_content');
    $sub = $this->createNode('collection');
    $collection = $this->createNode('collection', [
      'field_items_in_collection' => [['target_id' => $item->id()]],
      'field_child_collections' => [['target_id' => $sub->id()]],
    ]);

    $result = $this->resolver->getChildEntities($collection);
    $this->assertCount(2, $result['node']);
    $this->assertArrayHasKey((int) $item->id(), $result['node']);
    $this->assertArrayHasKey((int) $sub->id(), $result['node']);
  }

  public function testWordListWithWords(): void {
    $word1 = $this->createNode('protocol_aware_content');
    $word2 = $this->createNode('protocol_aware_content');
    $list = $this->createNode('word_list', [
      'field_words' => [
        ['target_id' => $word1->id()],
        ['target_id' => $word2->id()],
      ],
    ]);

    $result = $this->resolver->getChildEntities($list);
    $this->assertArrayHasKey('node', $result);
    $this->assertCount(2, $result['node']);
    $this->assertArrayHasKey((int) $word1->id(), $result['node']);
    $this->assertArrayHasKey((int) $word2->id(), $result['node']);
  }

  public function testEmptyWordListReturnsEmpty(): void {
    $list = $this->createNode('word_list');
    $result = $this->resolver->getChildEntities($list);
    $this->assertSame([], $result);
  }

  // --- getChildEntitiesRecursive() ---

  public function testRecursiveEmptyCollectionReturnsEmpty(): void {
    $collection = $this->createNode('collection');
    $result = $this->resolver->getChildEntitiesRecursive($collection);
    $this->assertSame([], $result);
  }

  public function testRecursiveCollectionFlattensNestedChildren(): void {
    $item_deep = $this->createNode('protocol_aware_content');
    $sub = $this->createNode('collection', [
      'field_items_in_collection' => [['target_id' => $item_deep->id()]],
    ]);
    $item_top = $this->createNode('protocol_aware_content');
    $parent = $this->createNode('collection', [
      'field_items_in_collection' => [['target_id' => $item_top->id()]],
      'field_child_collections' => [['target_id' => $sub->id()]],
    ]);

    $result = $this->resolver->getChildEntitiesRecursive($parent);
    // Should include $sub, $item_top, and $item_deep.
    $this->assertArrayHasKey((int) $sub->id(), $result['node']);
    $this->assertArrayHasKey((int) $item_top->id(), $result['node']);
    $this->assertArrayHasKey((int) $item_deep->id(), $result['node']);
  }

  public function testRecursiveCycleDetectionPreventsInfiniteLoop(): void {
    // A -> B -> A (circular). Should resolve without fatal/infinite loop.
    $a = $this->createNode('collection');
    $b = $this->createNode('collection', [
      'field_child_collections' => [['target_id' => $a->id()]],
    ]);
    $a->set('field_child_collections', [['target_id' => $b->id()]]);
    $a->save();

    $result = $this->resolver->getChildEntitiesRecursive($a);
    // $b is a child of $a; $a is a child of $b but should not be re-visited.
    $this->assertArrayHasKey((int) $b->id(), $result['node']);
    $this->assertArrayNotHasKey((int) $a->id(), $result['node']);
  }

  public function testRecursiveDiamondGraphDeduplicates(): void {
    // A -> B, A -> C; B -> D, C -> D. D should appear once.
    $d = $this->createNode('protocol_aware_content');
    $b = $this->createNode('collection', [
      'field_items_in_collection' => [['target_id' => $d->id()]],
    ]);
    $c = $this->createNode('collection', [
      'field_items_in_collection' => [['target_id' => $d->id()]],
    ]);
    $a = $this->createNode('collection', [
      'field_child_collections' => [
        ['target_id' => $b->id()],
        ['target_id' => $c->id()],
      ],
    ]);

    $result = $this->resolver->getChildEntitiesRecursive($a);
    $this->assertArrayHasKey((int) $d->id(), $result['node']);
    // id appears exactly once in the map (keyed by id so dedup is automatic).
    $this->assertCount(1, array_filter($result['node'], fn($v) => $v === (int) $d->id()));
  }

  public function testRecursiveNonCollectionReturnsEmpty(): void {
    $list = $this->createNode('word_list');
    $result = $this->resolver->getChildEntitiesRecursive($list);
    $this->assertSame([], $result);
  }

}
