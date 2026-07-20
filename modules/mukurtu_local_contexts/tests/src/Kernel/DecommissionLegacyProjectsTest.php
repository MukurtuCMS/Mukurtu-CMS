<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_local_contexts\Form\DecommissionLegacyProjectsConfirmForm;
use Drupal\mukurtu_local_contexts\Form\ManageSupportedProjectsSite;
use Drupal\node\Entity\Node;

/**
 * Tests the legacy project decommission validation and confirm flow.
 *
 * @group mukurtu_local_contexts
 */
class DecommissionLegacyProjectsTest extends LocalContextsTestBase {

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
   * Invoke the protected submitDecommission() method for testing.
   */
  protected function invokeSubmitDecommission(array $selectedProjects, FormState $form_state): void {
    $form_object = ManageSupportedProjectsSite::create($this->container);
    $method = new \ReflectionMethod($form_object, 'submitDecommission');
    $method->setAccessible(TRUE);
    $method->invoke($form_object, [], $selectedProjects, NULL, $form_state);
  }

  /**
   * A user without the dedicated permission is rejected outright.
   */
  public function testSubmitDecommissionRequiresPermission() {
    // Consume uid 1 (which bypasses all permission checks) with a throwaway
    // user first, so the actual test user's permission is genuinely
    // exercised.
    $this->createUser();
    $user = $this->createUser();
    $this->setCurrentUser($user);

    $this->seedSiteProject('default_tk', 'Default Legacy');

    $form_state = new FormState();
    $this->invokeSubmitDecommission(['default_tk'], $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission');
    $this->assertEmpty($tempstore->get($user->id()));
  }

  /**
   * A legacy project with zero references is accepted and queued.
   */
  public function testSubmitDecommissionAcceptsValidLegacyProject() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('default_tk', 'Default Legacy');

    $form_state = new FormState();
    $this->invokeSubmitDecommission(['default_tk'], $form_state);

    $this->assertFalse($form_state->hasAnyErrors());
    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission');
    $pending = $tempstore->get($user->id());
    $this->assertEquals(['default_tk'], $pending['project_ids']);
    $this->assertEquals('site', $pending['scope']);
    $this->assertNull($pending['group_id']);
  }

  /**
   * A non-legacy (real) project is rejected, not queued.
   */
  public function testSubmitDecommissionRejectsNonLegacyProject() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('real-project-1', 'Real Project');

    $form_state = new FormState();
    $this->invokeSubmitDecommission(['real-project-1'], $form_state);

    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission');
    $this->assertEmpty($tempstore->get($user->id()));
    $this->assertNotEmpty($this->container->get('messenger')->all());
  }

  /**
   * A legacy project still referenced by content is rejected, not queued.
   */
  public function testSubmitDecommissionRejectsProjectStillInUse() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedLabel('label_1', 'default_tk', 'Label One');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['default_tk:label_1:label']);
    $node->save();

    $form_state = new FormState();
    $this->invokeSubmitDecommission(['default_tk'], $form_state);

    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission');
    $this->assertEmpty($tempstore->get($user->id()));
  }

  /**
   * The confirm form's access() callback requires both permission and a
   * non-empty pending tempstore entry.
   */
  public function testConfirmFormAccess() {
    $this->createUser();
    $noPermUser = $this->createUser();
    $permUser = $this->createUser(['administer local contexts legacy projects']);

    $this->seedSiteProject('default_tk', 'Default Legacy');

    $form_object = DecommissionLegacyProjectsConfirmForm::create($this->container);

    // No permission - forbidden regardless of tempstore state.
    $this->assertFalse($form_object->access($noPermUser)->isAllowed());

    // Permission, but nothing pending - not allowed.
    $this->assertFalse($form_object->access($permUser)->isAllowed());

    // Permission and a pending entry - allowed.
    $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission')->set($permUser->id(), [
      'scope' => 'site',
      'group_id' => NULL,
      'project_ids' => ['default_tk'],
    ]);
    $this->assertTrue($form_object->access($permUser)->isAllowed());
  }

  /**
   * submitForm() successfully decommissions a valid, still-zero-reference
   * legacy project: all its DB rows are removed.
   */
  public function testConfirmFormSubmitDecommissionsValidProject() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedLabel('label_1', 'default_tk', 'Label One');

    $form_object = DecommissionLegacyProjectsConfirmForm::create($this->container);
    $pending_property = new \ReflectionProperty($form_object, 'pending');
    $pending_property->setAccessible(TRUE);
    $pending_property->setValue($form_object, [
      'scope' => 'site',
      'group_id' => NULL,
      'project_ids' => ['default_tk'],
    ]);

    $form = [];
    $form_state = new FormState();
    $form_object->submitForm($form, $form_state);

    $db = $this->container->get('database');
    $this->assertFalse((bool) $db->select('mukurtu_local_contexts_projects', 'p')->condition('id', 'default_tk')->countQuery()->execute()->fetchField());
    $this->assertFalse((bool) $db->select('mukurtu_local_contexts_labels', 'l')->condition('project_id', 'default_tk')->countQuery()->execute()->fetchField());
    $this->assertFalse($this->container->get('mukurtu_local_contexts.supported_project_manager')->isSiteSupportedProject('default_tk'));

    // Tempstore entry is cleared after processing.
    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.decommission');
    $this->assertEmpty($tempstore->get($user->id()));
  }

  /**
   * submitForm() re-validates before deleting: if a project became
   * referenced by content between selection and confirmation, it's left
   * untouched rather than deleted.
   */
  public function testConfirmFormSubmitBlocksIfNowInUse() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('default_tk', 'Default Legacy');
    $this->seedLabel('label_1', 'default_tk', 'Label One');

    $form_object = DecommissionLegacyProjectsConfirmForm::create($this->container);
    $pending_property = new \ReflectionProperty($form_object, 'pending');
    $pending_property->setAccessible(TRUE);
    $pending_property->setValue($form_object, [
      'scope' => 'site',
      'group_id' => NULL,
      'project_ids' => ['default_tk'],
    ]);

    // Something now references the project, after selection but before
    // confirmation.
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['default_tk:label_1:label']);
    $node->save();

    $form = [];
    $form_state = new FormState();
    $form_object->submitForm($form, $form_state);

    $db = $this->container->get('database');
    $this->assertTrue((bool) $db->select('mukurtu_local_contexts_projects', 'p')->condition('id', 'default_tk')->countQuery()->execute()->fetchField());
    $this->assertNotEmpty($this->container->get('messenger')->all());
  }

}
