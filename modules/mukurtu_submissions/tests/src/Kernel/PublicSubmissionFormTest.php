<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PublicSubmissionForm, the public/anonymous-facing form behind
 * /submit/{entity_type_id}/{bundle}.
 */
#[Group('mukurtu_submissions')]
class PublicSubmissionFormTest extends ProtocolAwareEntityTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_submissions',
  ];

  /**
   * The Cultural Protocol field must never appear on the public submission
   * form, however the target bundle's "submission" display would otherwise
   * be configured - PublicSubmissionForm::EXCLUDED_FIELDS strips it
   * unconditionally. Deliberately only exercises buildForm(): the full
   * submitForm() save path for a *moderated* entity additionally requires
   * the impersonated uid 1 to hold the workflow's transition permissions
   * (normally granted by the site's own install profile, out of scope for
   * this kernel test's isolated environment), so that path is covered
   * instead by PublicSubmissionFormUnmoderatedSaveTest.
   */
  public function testCulturalProtocolFieldNeverAppearsOnForm(): void {
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'protocol_aware_content');
    $this->installEntitySchema('content_moderation_state');

    $form_object = \Drupal::classResolver(PublicSubmissionForm::class);
    $form = $form_object->buildForm([], new FormState(), 'node', 'protocol_aware_content');

    $this->assertArrayNotHasKey('field_cultural_protocols', $form);
  }

}
