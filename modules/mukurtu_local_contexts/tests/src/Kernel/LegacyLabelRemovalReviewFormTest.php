<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_local_contexts\Form\LegacyLabelRemovalReviewForm;
use Drupal\node\Entity\Node;

/**
 * Tests LegacyLabelRemovalReviewForm.
 *
 * @group mukurtu_local_contexts
 */
class LegacyLabelRemovalReviewFormTest extends LocalContextsTestBase {

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
   * access() requires permission, a legacy project, and a valid ref_type.
   */
  public function testAccess() {
    $this->createUser();
    $noPermUser = $this->createUser();
    $permUser = $this->createUser(['administer local contexts legacy projects']);

    $this->seedSiteProject('default_tk', 'Legacy One');
    $this->seedSiteProject('real-project-1', 'Real Project');

    $form_object = LegacyLabelRemovalReviewForm::create($this->container);

    $this->assertFalse($form_object->access($noPermUser, 'default_tk', 'label')->isAllowed());
    $this->assertTrue($form_object->access($permUser, 'default_tk', 'label')->isAllowed());
    $this->assertFalse($form_object->access($permUser, 'default_tk', 'invalid_type')->isAllowed());
    $this->assertFalse($form_object->access($permUser, 'real-project-1', 'label')->isAllowed());
  }

  /**
   * buildForm() only lists nodes that actually reference the given label.
   */
  public function testBuildFormListsOnlyReferencingNodes() {
    $this->seedSiteProject('sitewide_tk', 'Legacy Two');
    $this->seedLabel('label_1', 'sitewide_tk', 'Label One');

    $referencing = $this->createTestNode();
    $referencing->set('field_local_contexts_labels_and_notices', ['sitewide_tk:label_1:label']);
    $referencing->save();

    $unrelated = $this->createTestNode();
    $unrelated->save();

    $form_object = LegacyLabelRemovalReviewForm::create($this->container);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, 'sitewide_tk', 'label', 'label_1');

    $this->assertArrayHasKey((string) $referencing->id(), $form['items']['#options']);
    $this->assertArrayNotHasKey((string) $unrelated->id(), $form['items']['#options']);
  }

  /**
   * Submitting with nothing checked shows an error and writes no tempstore.
   */
  public function testSubmitWithNothingSelected() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('comm_1_tk', 'Legacy Three');
    $this->seedLabel('label_1', 'comm_1_tk', 'Label One');
    $node = $this->createTestNode();
    $node->set('field_local_contexts_labels_and_notices', ['comm_1_tk:label_1:label']);
    $node->save();

    $form_object = LegacyLabelRemovalReviewForm::create($this->container);
    $form_state = new FormState();
    $form_object->buildForm([], $form_state, 'comm_1_tk', 'label', 'label_1');
    $form_state->setValue('items', []);

    $form = [];
    $form_object->submitForm($form, $form_state);

    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.label_removal');
    $this->assertEmpty($tempstore->get($user->id()));
    $this->assertNotEmpty($this->container->get('messenger')->all());
  }

  /**
   * Submitting a subset stores exactly that subset, not all referencing
   * nodes.
   */
  public function testSubmitWithSubsetSelected() {
    $this->createUser();
    $user = $this->createUser(['administer local contexts legacy projects']);
    $this->setCurrentUser($user);

    $this->seedSiteProject('comm_2_tk', 'Legacy Four');
    $this->seedLabel('label_1', 'comm_2_tk', 'Label One');

    $selected = $this->createTestNode();
    $selected->set('field_local_contexts_labels_and_notices', ['comm_2_tk:label_1:label']);
    $selected->save();

    $notSelected = $this->createTestNode();
    $notSelected->set('field_local_contexts_labels_and_notices', ['comm_2_tk:label_1:label']);
    $notSelected->save();

    $form_object = LegacyLabelRemovalReviewForm::create($this->container);
    $form_state = new FormState();
    $form_object->buildForm([], $form_state, 'comm_2_tk', 'label', 'label_1');
    $form_state->setValue('items', [
      (string) $selected->id() => (string) $selected->id(),
      (string) $notSelected->id() => 0,
    ]);

    $form = [];
    $form_object->submitForm($form, $form_state);

    $tempstore = $this->container->get('tempstore.private')->get('mukurtu_local_contexts.label_removal');
    $pending = $tempstore->get($user->id());
    $this->assertEquals([(int) $selected->id()], $pending['node_ids']);
    $this->assertEquals('comm_2_tk', $pending['project_id']);
    $this->assertEquals('label', $pending['ref_type']);
    $this->assertEquals('label_1', $pending['ref_id']);
  }

}
