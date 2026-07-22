<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_local_contexts\Form\LegacyLabelRemovalConfirmForm;
use Drupal\node\Entity\Node;

/**
 * Tests LegacyLabelRemovalConfirmForm.
 *
 * @group mukurtu_local_contexts
 */
class LegacyLabelRemovalConfirmFormTest extends LocalContextsTestBase {

  /**
   * Creates and saves a new test node.
   */
  protected function createTestNode(): Node {
    $node = Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => $this->randomString(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Sets the protected $pending property via reflection, bypassing
   * buildForm() (which needs a full route/request context we don't need
   * for testing submitForm()/access() in isolation).
   */
  protected function setPending($form_object, array $pending): void {
    $property = new \ReflectionProperty($form_object, 'pending');
    $property->setAccessible(TRUE);
    $property->setValue($form_object, $pending);
  }

  /**
   * Gets the node IDs passed to the queued LegacyLabelRemovalBatch::run()
   * operation, without actually executing it - submitForm() only queues
   * the batch via batch_set(), it doesn't run synchronously.
   */
  protected function getQueuedNodeIds(): array {
    $batch = batch_get();
    $operations = $batch['sets'][0]['operations'] ?? [];
    $args = $operations[0][1] ?? [];
    return $args[3] ?? [];
  }

  /**
   * access() requires both permission and a non-empty pending tempstore
   * entry.
   */
  public function testAccess() {
    $this->createUser();
    $noPermUser = $this->createUser();
    $permUser = $this->createUser(['administer local contexts legacy projects']);

    $form_object = LegacyLabelRemovalConfirmForm::create($this->container);

    $this->assertFalse($form_object->access($noPermUser)->isAllowed());
    $this->assertFalse($form_object->access($permUser)->isAllowed());

    $this->container->get('tempstore.private')->get('mukurtu_local_contexts.label_removal')->set($permUser->id(), [
      'project_id' => 'default_tk',
      'ref_type' => 'label',
      'ref_id' => 'label_1',
      'node_ids' => [1],
    ]);
    $this->assertTrue($form_object->access($permUser)->isAllowed());
  }

  /**
   * submitForm() queues the batch with exactly the selected, still-
   * referencing nodes, and clears the tempstore entry.
   */
  public function testSubmitFormQueuesSelectedNodes() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('sitewide_tk', 'Legacy Two');
    $this->seedLabel('label_1', 'sitewide_tk', 'Label One');

    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['sitewide_tk:label_1:label']);
    $node->save();

    $form_object = LegacyLabelRemovalConfirmForm::create($this->container);
    $this->setPending($form_object, [
      'project_id' => 'sitewide_tk',
      'ref_type' => 'label',
      'ref_id' => 'label_1',
      'node_ids' => [(int) $node->id()],
    ]);

    $form = [];
    $form_state = new FormState();
    $form_object->submitForm($form, $form_state);

    $this->assertEquals([(int) $node->id()], $this->getQueuedNodeIds());

    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.label_removal');
    $this->assertEmpty($tempstore->get($user->id()));
  }

  /**
   * Re-validation: a node that was selected during review but no longer
   * references the label by confirmation time (something else resolved it
   * in the interim) is excluded from the queued batch entirely.
   */
  public function testSubmitFormExcludesAlreadyResolvedNode() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('comm_1_tk', 'Legacy Three');
    $this->seedLabel('label_1', 'comm_1_tk', 'Label One');

    // Still references the label - should be included.
    $stillReferencing = $this->createTestNode();
    $stillReferencing->set('field_local_contexts_labels_and_notices', ['comm_1_tk:label_1:label']);
    $stillReferencing->save();

    // Selected during review, but by confirmation time no longer
    // references the label (e.g. a separate remap/removal pass already
    // resolved it) - should be excluded.
    $alreadyResolved = $this->createTestNode();
    $alreadyResolved->save();

    $form_object = LegacyLabelRemovalConfirmForm::create($this->container);
    $this->setPending($form_object, [
      'project_id' => 'comm_1_tk',
      'ref_type' => 'label',
      'ref_id' => 'label_1',
      'node_ids' => [(int) $stillReferencing->id(), (int) $alreadyResolved->id()],
    ]);

    $form = [];
    $form_state = new FormState();
    $form_object->submitForm($form, $form_state);

    $this->assertEquals([(int) $stillReferencing->id()], $this->getQueuedNodeIds());
  }

}
