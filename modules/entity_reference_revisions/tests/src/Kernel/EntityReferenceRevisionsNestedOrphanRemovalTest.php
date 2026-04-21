<?php

namespace Drupal\Tests\entity_reference_revisions\Kernel;

use Drupal\entity_composite_relationship_test\Entity\EntityTestCompositeRelationship;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests orphan purger with nested/multi-level entity reference revisions.
 *
 * This test addresses the scenario where composite entities can reference
 * other composite entities (e.g., paragraphs inside paragraphs).
 *
 * @group entity_reference_revisions
 */
#[RunTestsInSeparateProcesses]
#[Group('entity_reference_revisions')]
class EntityReferenceRevisionsNestedOrphanRemovalTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'field',
    'entity_reference_revisions',
    'entity_composite_relationship_test',
  ];

  /**
   * The orphan purger service.
   *
   * @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsOrphanPurger
   */
  protected $orphanPurger;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The composite entity storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $compositeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_test_composite');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'field']);

    $this->orphanPurger = $this->container->get('entity_reference_revisions.orphan_purger');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->compositeStorage = $this->entityTypeManager->getStorage('entity_test_composite');

    $this->setupContentType();
  }

  /**
   * Tests nested composites aren't auto. purged when parent rev. is deleted.
   *
   * Multiple manual purges of orphans are required.
   */
  public function testNestedOrphansNotAutomaticallyPurgedAfterParentRevisionDeletion() {
    $test_data = $this->setupNestedCompositeRevisions();

    $node_rev_1_id = $test_data['node_rev_1_id'];
    $node_rev_2_id = $test_data['node_rev_2_id'];
    $composite_a_id = $test_data['composite_a_id'];
    $composite_b_id = $test_data['composite_b_id'];

    // Delete Node revision 1.
    $this->nodeStorage->deleteRevision($node_rev_1_id);
    $this->assertNull($this->nodeStorage->loadRevision($node_rev_1_id));

    // Automatically, 1 revision of A and 1 revision of B are deleted.
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a_id);
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b_id);

    // Delete Node revision 2.
    $this->nodeStorage->deleteRevision($node_rev_2_id);
    $this->assertNull($this->nodeStorage->loadRevision($node_rev_2_id));

    // After deleting rev 2, composite revisions are still present, because
    // they are the default revisions. See
    // \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem::deleteRevision().
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a_id);
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b_id);

    // Run the orphan purger (first pass).
    $this->runOrphanPurger('entity_test_composite');

    // The first purger run should delete all remaining revisions of A.
    $this->assertRevisionCount(0, 'entity_test_composite', $composite_a_id,
      'Composite A should have 0 revisions after first purger run.');

    // However, nested composite B still has 2 revisions remaining.
    // This is because B's parent (A) was just deleted in this purger run,
    // so B couldn't be detected as orphaned yet.
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b_id,
      'Composite B (nested) should still have 1 revision after first purger run.');

    // Run the orphan purger a second time.
    $this->runOrphanPurger('entity_test_composite');

    // The second purger run should now delete the remaining revision of B.
    $this->assertRevisionCount(0, 'entity_test_composite', $composite_b_id,
      'Composite B (nested) should have 0 revisions after second purger run.');
  }

  /**
   * Tests nested composites are purged on cron run when parent rev. is deleted.
   */
  public function testNestedOrphansPurgedOnCronAfterParentRevisionDeletion() {
    $test_data = $this->setupNestedCompositeRevisions();

    $node_rev_1_id = $test_data['node_rev_1_id'];
    $node_rev_2_id = $test_data['node_rev_2_id'];
    $composite_a_id = $test_data['composite_a_id'];
    $composite_b_id = $test_data['composite_b_id'];

    // Delete Node revision 1.
    $this->nodeStorage->deleteRevision($node_rev_1_id);
    $this->assertNull($this->nodeStorage->loadRevision($node_rev_1_id));

    // 1 revision of A and 1 revision of B should have been deleted.
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a_id);
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b_id);

    // Delete Node revision 2.
    $this->nodeStorage->deleteRevision($node_rev_2_id);
    $this->assertNull($this->nodeStorage->loadRevision($node_rev_2_id));

    // After deleting rev 2, composite revisions are still present, because
    // they are the default revisions. See
    // \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem::deleteRevision().
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a_id);
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b_id);

    // Run cron to process the orphan purger queue.
    $this->container->get('cron')->run();

    // We expect the processing of the orphan purger queue by the cron to have
    // removed all revisions of A and B.
    $this->assertRevisionCount(0, 'entity_test_composite', $composite_a_id,
      'Composite A should have 0 revision after orphan queue is processed by cron.');
    $this->assertRevisionCount(0, 'entity_test_composite', $composite_b_id,
      'Composite B (nested) should have 0 revision after orphan queue is processed by cron.');
  }

  /**
   * Sets up nested composite revisions for testing.
   *
   * Creates a complex structure:
   * - Node rev 1 -> Composite A rev 1 -> Composite B rev 1
   * - Node rev 2 -> Composite A rev 3 -> Composite B rev 3
   * - Node rev 3 -> NULL (no composite reference)
   *
   * With orphaned revisions: A rev 2, B rev 2
   *
   * @return array
   *   Test data with entity IDs and revision IDs.
   */
  protected function setupNestedCompositeRevisions(): array {
    // Create the innermost composite entity (B).
    $composite_b = EntityTestCompositeRelationship::create([
      'name' => 'Inner composite B',
      'parent_type' => 'entity_test_composite',
      'parent_field_name' => 'field_nested_composite',
    ]);
    $composite_b->save();

    // Create the middle level composite entity (A) that references B.
    $composite_a = EntityTestCompositeRelationship::create([
      'name' => 'Middle composite A',
      'parent_type' => 'node',
      'parent_field_name' => 'field_composite_entity',
      'field_nested_composite' => $composite_b,
    ]);
    $composite_a->save();

    // Update B to reflect its actual parent.
    $composite_b->set('parent_id', $composite_a->id());
    $composite_b->save();

    // Create Node revision 1 that references A (which contains B).
    $node = Node::create([
      'type' => 'revisionable',
      'title' => 'Test node with nested composites',
      'field_composite_entity' => $composite_a,
    ]);
    $node->save();
    $node_rev_1_id = $node->getRevisionId();

    // Verify initial state: 1 revision each.
    $this->assertRevisionCount(1, 'entity_test_composite', $composite_a->id());
    $this->assertRevisionCount(1, 'entity_test_composite', $composite_b->id());

    // Create new revision of A: this automatically creates a new revision of B.
    $composite_a = EntityTestCompositeRelationship::load($composite_a->id());
    $composite_a->setNewRevision(TRUE);
    $composite_a->set('name', 'Middle composite A - rev 2');
    $composite_a->save();

    // Now we have: A rev 1, A rev 2; B rev 1, B rev 2.
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a->id());
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_b->id());

    // Create a standalone new revision of B (rev 3) - this becomes orphaned.
    $composite_b = EntityTestCompositeRelationship::load($composite_b->id());
    $composite_b->setNewRevision(TRUE);
    $composite_b->set('name', 'Inner composite B - rev 3');
    $composite_b->set('parent_id', $composite_a->id());
    $composite_b->save();

    // Now we have: A rev 1, A rev 2; B rev 1, B rev 2, B rev 3.
    $this->assertRevisionCount(2, 'entity_test_composite', $composite_a->id());
    $this->assertRevisionCount(3, 'entity_test_composite', $composite_b->id());

    // Create Node revision 2 that references the current A.
    // This automatically creates new revisions: A rev 3.
    $node->setNewRevision(TRUE);
    $node->set('field_composite_entity', $composite_a);
    $node->set('title', 'Test node with nested composites - rev 2');
    $node->save();
    $node_rev_2_id = $node->getRevisionId();

    // Now we have: A rev 1, A rev 2, A rev 3; B rev 1, B rev 2, B rev 3.
    $this->assertRevisionCount(3, 'entity_test_composite', $composite_a->id());
    $this->assertRevisionCount(3, 'entity_test_composite', $composite_b->id());

    // Create Node revision 3 that doesn't reference A anymore.
    $node->setNewRevision(TRUE);
    $node->set('field_composite_entity', NULL);
    $node->set('title', 'Test node with nested composites - rev 3');
    $node->save();

    // Composite revisions should still exist.
    $this->assertRevisionCount(3, 'entity_test_composite', $composite_a->id());
    $this->assertRevisionCount(3, 'entity_test_composite', $composite_b->id());

    return [
      'node_rev_1_id' => $node_rev_1_id,
      'node_rev_2_id' => $node_rev_2_id,
      'composite_a_id' => $composite_a->id(),
      'composite_b_id' => $composite_b->id(),
    ];
  }

  /**
   * Sets up the content type with entity reference revisions fields.
   */
  protected function setupContentType(): void {
    // Create a revisionable content type.
    $node_type = NodeType::create([
      'type' => 'revisionable',
      'name' => 'Revisionable',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    // Create the entity reference revisions field on nodes.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_composite_entity',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'entity_test_composite',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'revisionable',
      'label' => 'Composite Entity',
    ]);
    $field->save();

    // Create a nested entity reference revisions field on composite entities.
    // This allows composites to reference other composites.
    $field_storage_nested = FieldStorageConfig::create([
      'field_name' => 'field_nested_composite',
      'entity_type' => 'entity_test_composite',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'entity_test_composite',
      ],
    ]);
    $field_storage_nested->save();

    $field_nested = FieldConfig::create([
      'field_storage' => $field_storage_nested,
      'bundle' => 'entity_test_composite',
      'label' => 'Nested Composite',
    ]);
    $field_nested->save();
  }

  /**
   * Runs the orphan purger for a specific entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID to purge orphans for.
   */
  protected function runOrphanPurger(string $entity_type_id): void {
    $context = [];
    $this->orphanPurger->deleteOrphansBatchOperation($entity_type_id, $context);
  }

  /**
   * Asserts the revision count of a certain entity.
   *
   * @param int $expected
   *   The expected count.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param string $message
   *   Optional assertion message.
   */
  protected function assertRevisionCount(int $expected, string $entity_type_id, int $entity_id, string $message = ''): void {
    $id_field = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    $revision_count = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->condition($id_field, $entity_id)
      ->allRevisions()
      ->count()
      ->accessCheck(FALSE)
      ->execute();

    if (empty($message)) {
      $message = "Expected $expected revisions for $entity_type_id:$entity_id, found $revision_count.";
    }

    $this->assertEquals($expected, $revision_count, $message);
  }

}
