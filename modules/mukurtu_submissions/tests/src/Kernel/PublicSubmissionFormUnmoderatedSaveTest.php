<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Covers PublicSubmissionForm's save path: the resulting node ends up
 * unpublished and owned by the configured service account, and submitter
 * PII lands only on the separate mukurtu_submission entity, never on the
 * content entity itself.
 *
 * Kept as a plain (non-moderated) bundle so entity->validate() never hits
 * content_moderation's transition-permission constraint, which - for a
 * *moderated* entity - depends on the impersonated uid 1 holding workflow
 * transition permissions that only exist by convention of a full site
 * install, not by anything content_moderation itself grants uid 1
 * automatically. The moderation-state pinning itself, and the Cultural
 * Protocol field's exclusion from the built form, are covered separately
 * by PublicSubmissionFormTest.
 *
 * @group mukurtu_submissions
 */
class PublicSubmissionFormUnmoderatedSaveTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'node',
    'mukurtu_submissions',
  ];

  protected string $bundle = 'submission_flow_test';

  /**
   * The service account submissions are meant to be owned by.
   */
  protected int $serviceAccountUid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('mukurtu_submission');

    NodeType::create(['type' => $this->bundle, 'name' => 'Submission flow test'])->save();

    // Created first so it becomes uid 1 - PublicSubmissionForm::asSuperuser()
    // unconditionally loads and impersonates uid 1 during validate()/save(),
    // and would fatal on a NULL account if no user occupied that id yet.
    // Deliberately distinct from the service account below, so a passing
    // ownership assertion can't be masked by both happening to be the same
    // user.
    User::create(['name' => 'root-stand-in', 'status' => 1])->save();

    $service_account = User::create(['name' => 'Service Account', 'status' => 0]);
    $service_account->save();
    $this->serviceAccountUid = (int) $service_account->id();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', $this->serviceAccountUid)
      ->save();
  }

  /**
   * Builds, validates, and submits PublicSubmissionForm exactly as Form API
   * would for a fresh, valid submission.
   */
  protected function submit(): Node {
    $form_state = (new FormState())->setValues([
      'title' => [['value' => 'A visitor submission']],
      'submitter_name' => 'Jane Visitor',
      'submitter_email' => 'jane@example.com',
    ]);

    $form_object = \Drupal::classResolver(PublicSubmissionForm::class);
    $form = $form_object->buildForm([], $form_state, 'node', $this->bundle);
    $form_object->validateForm($form, $form_state);
    $this->assertEmpty($form_state->getErrors(), 'Submission should validate cleanly.');
    $form_object->submitForm($form, $form_state);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => $this->bundle]);
    $node = reset($nodes);
    $this->assertNotFalse($node, 'Submission should have created a node.');
    return $node;
  }

  public function testSubmissionCreatesUnpublishedNodeOwnedByServiceAccount(): void {
    $node = $this->submit();

    $this->assertFalse($node->isPublished());
    $this->assertEquals($this->serviceAccountUid, (int) $node->getOwnerId());
  }

  public function testSubmitterPiiIsStoredOnlyOnTheSubmissionEntity(): void {
    $node = $this->submit();

    $submissions = $this->entityTypeManager->getStorage('mukurtu_submission')->loadByProperties([
      'target_entity_type' => 'node',
      'target_id' => $node->id(),
    ]);
    $submission = reset($submissions);
    $this->assertNotFalse($submission, 'A mukurtu_submission entity should have been created for the node.');
    $this->assertEquals('Jane Visitor', $submission->getSubmitterName());
    $this->assertEquals('jane@example.com', $submission->getSubmitterEmail());

    // The node itself has no field capable of holding this information -
    // the separation is structural, not just a matter of what the form
    // happens to populate.
    $this->assertFalse($node->hasField('submitter_name'));
    $this->assertFalse($node->hasField('submitter_email'));
  }

}
