<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use Drupal\mukurtu_workflows\Form\ReviewStateForm;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies a visitor's submission can actually be driven end to end -
 * submit -&gt; draft -&gt; reviewer transition -&gt; published - through the site's
 * REAL shipped moderation workflow (mukurtu_default_content_workflow), for
 * a bundle other than digital_heritage with its OWN required custom field
 * (not just the special-cased field_cultural_protocols every bundle
 * shares). #1890 only ever exercised this against digital_heritage;
 * PublicSubmissionForm and ReviewStateForm's required-field validation
 * were never previously re-confirmed against a bundle PR3's
 * generalization work newly supports.
 *
 * ReviewStateFormTest (modules/mukurtu_workflows) already covers the
 * validation-gap logic itself in isolation, against an ad-hoc workflow
 * unconnected to mukurtu_submissions - this test is the missing link: the
 * same logic, reached via PublicSubmissionForm's own node creation, using
 * the actual config a site ships with.
 */
#[Group('mukurtu_submissions')]
class PublishingWorkflowIntegrationTest extends ProtocolAwareEntityTestBase {

  use ContentModerationTestTrait;

  const REQUIRED_FIELD = 'field_test_required';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_group',
    'mukurtu_submissions',
    'mukurtu_workflows',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('mukurtu_submission');
    $this->installSchema('mukurtu_workflows', ['mukurtu_review_note']);
    // Loads the real shipped workflows.workflow.mukurtu_default_content_workflow
    // config directly (not installConfig(['mukurtu_workflows']) - that also
    // installs the module's views.view.mukurtu_workflow_overview, whose
    // schema depends on view field plugins this test's $modules list has
    // no reason to carry) - the point of this test is to verify against
    // what a site actually ships with, not an ad-hoc test fixture workflow.
    $workflow_path = \Drupal::service('extension.list.module')->getPath('mukurtu_workflows')
      . '/config/install/workflows.workflow.mukurtu_default_content_workflow.yml';
    $workflow_data = Yaml::decode(file_get_contents($workflow_path));
    unset($workflow_data['_core'], $workflow_data['dependencies']);
    // The shipped default bundle list (article, digital_heritage, ...)
    // doesn't exist in this test's environment - reset to empty and add
    // only the one bundle this test actually needs (protocol_aware_content,
    // created by ProtocolAwareEntityTestBase - not moderated by default),
    // exactly as a site administrator would via WorkflowSettingsForm.
    $workflow_data['type_settings']['entity_types'] = [];
    $workflow = Workflow::create($workflow_data);
    $workflow->save();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'protocol_aware_content');

    // Shipped by mukurtu_submissions as default config on a real site
    // (config/install/core.entity_form_mode.node.submission.yml) - see
    // MukurtuSubmissionsKernelTestBase's own docblock for why kernel tests
    // create this directly rather than installConfig(['mukurtu_submissions']).
    if (!EntityFormMode::load('node.submission')) {
      EntityFormMode::create([
        'id' => 'node.submission',
        'label' => 'Submission',
        'targetEntityType' => 'node',
      ])->save();
    }

    // A required field of the bundle's own, distinct from
    // field_cultural_protocols (every CulturalProtocolControlled bundle
    // has that one, and PublicSubmissionForm already special-cases it) -
    // this is what actually proves required-field validation generalizes
    // to arbitrary bundle-specific fields, not just the one field the
    // form already knows how to handle.
    FieldStorageConfig::create([
      'field_name' => static::REQUIRED_FIELD,
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => static::REQUIRED_FIELD,
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Test Required Field',
      'required' => TRUE,
    ])->save();

    $submission_display = $this->container->get('entity_display.repository')
      ->getFormDisplay('node', 'protocol_aware_content', 'submission');
    $submission_display->setComponent('title', ['type' => 'string_textfield', 'weight' => -10]);
    $submission_display->setComponent(static::REQUIRED_FIELD, ['type' => 'string_textfield', 'weight' => 0]);
    $submission_display->save();

    SubmissionSettings::create([
      'id' => 'protocol_aware_content',
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'protocol_aware_content',
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();

    // Grants the transitions used below directly, matching
    // ReviewStateFormTest's own precedent - this test is about validation
    // generalizing correctly, not re-proving transition-permission
    // enforcement (already covered by ProtocolAwareStateTransitionValidation's
    // own tests).
    $role = Role::load('authenticated');
    $role->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $role->grantPermission('use mukurtu_default_content_workflow transition publish');
    $role->save();
  }

  /**
   * Builds, then validates (and submits, if validation raised no errors)
   * PublicSubmissionForm for protocol_aware_content, on a single form
   * object instance - mirroring how Drupal's own form processing pipeline
   * reuses one $form_object across build/validate/submit within a single
   * request, which PublicSubmissionForm's "$this->entity" reuse guard
   * (buildForm()'s "if (!$this->entity)" check) depends on.
   */
  protected function runSubmissionForm(array $values): FormState {
    $form_state = (new FormState())->setValues($values);
    $form_object = \Drupal::classResolver(PublicSubmissionForm::class);

    $form = $form_object->buildForm([], $form_state, 'node', 'protocol_aware_content');
    $form_object->validateForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $form_object->submitForm($form, $form_state);
    }

    return $form_state;
  }

  /**
   * Identical helper to ReviewStateFormTest's own - see that class for why
   * validateForm()/submitForm() are called directly rather than through
   * buildForm()'s button-rendering (the transition-permission check still
   * runs via $node->validate()'s ModerationStateConstraint, independent of
   * which buttons buildForm() would have rendered).
   */
  protected function runReviewStateForm(Node $node, string $to_state, string $note = ''): FormState {
    $form_state = (new FormState())->setValues([
      'node_id' => $node->id(),
      'from_state' => $node->get('moderation_state')->value,
      'note' => $note,
    ]);
    $form_state->setTriggeringElement(['#mukurtu_to_state' => $to_state]);

    $form_object = \Drupal::classResolver(ReviewStateForm::class);
    $form = [];
    $form_object->validateForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $form_object->submitForm($form, $form_state);
    }

    return $form_state;
  }

  /**
   * A visitor omitting the bundle's own required field must be blocked at
   * the submission form itself - never even reaching node creation -
   * exactly as core's node edit form would block it, generalized beyond
   * the one field (field_cultural_protocols) the form already special-cases.
   */
  public function testMissingRequiredFieldBlocksSubmission(): void {
    $form_state = $this->runSubmissionForm([
      'submitter_name' => 'Jane Visitor',
      'submitter_email' => 'jane@example.com',
      'title' => [['value' => 'My Submission']],
      // field_test_required deliberately omitted.
    ]);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey(static::REQUIRED_FIELD, $errors);

    $count = $this->container->get('entity_type.manager')->getStorage('node')->getQuery()->accessCheck(FALSE)->count()->execute();
    $this->assertEquals(0, $count, 'A blocked submission must never create a node.');
  }

  /**
   * Submits a complete form and returns the resulting node - shared setup
   * for the two review-step tests below, not itself a test.
   */
  protected function submitCompleteFormAndGetNode(): Node {
    $form_state = $this->runSubmissionForm([
      'submitter_name' => 'Jane Visitor',
      'submitter_email' => 'jane@example.com',
      'title' => [['value' => 'My Submission']],
      static::REQUIRED_FIELD => [['value' => 'filled in']],
    ]);

    $this->assertEmpty($form_state->getErrors());

    $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(['type' => 'protocol_aware_content']);
    $this->assertCount(1, $nodes);

    return reset($nodes);
  }

  /**
   * A complete submission creates the node already in the workflow's
   * "draft" state, unpublished, owned by the service account, with no
   * Cultural Protocol assigned yet - matching setPendingSubmissionState()'s
   * documented contract, now confirmed against the real shipped workflow
   * rather than just the ad-hoc one ReviewStateFormTest builds.
   */
  public function testCompleteSubmissionCreatesUnpublishedDraftNode(): void {
    $node = $this->submitCompleteFormAndGetNode();

    $this->assertFalse($node->isPublished());
    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertEmpty($node->getProtocols());
  }

  /**
   * The quick-publish review panel must block a submitted node with no
   * Cultural Protocol assigned, the same way ReviewStateFormTest already
   * proves for a hand-built node against an ad-hoc workflow - here for a
   * node PublicSubmissionForm itself created, against the real shipped
   * mukurtu_default_content_workflow.
   */
  public function testPublishWithoutProtocolIsBlockedForSubmittedNode(): void {
    $node = $this->submitCompleteFormAndGetNode();

    $form_state = $this->runReviewStateForm($node, 'published', 'attempted note');

    $this->assertNotEmpty($form_state->getErrors());

    $node = Node::load($node->id());
    $this->assertEquals('draft', $node->get('moderation_state')->value);
    $this->assertFalse($node->isPublished());
  }

  /**
   * Once a reviewer assigns a Cultural Protocol, the same submitted node
   * publishes normally - the generalized required-field validation above
   * doesn't block a legitimately complete, reviewer-approved submission.
   */
  public function testPublishWithProtocolSucceedsForSubmittedNode(): void {
    $node = $this->submitCompleteFormAndGetNode();

    $node->set('field_cultural_protocols', [
      'protocols' => CulturalProtocolItem::formatProtocols([1]),
      'sharing_setting' => 'all',
    ]);
    $node->save();

    $form_state = $this->runReviewStateForm($node, 'published', 'looks good');

    $this->assertEmpty($form_state->getErrors());

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->isPublished());
  }

}
