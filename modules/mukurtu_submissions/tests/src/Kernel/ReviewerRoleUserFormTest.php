<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_submissions\Form\SubmissionSettingsCollectionForm;

/**
 * The Submission Reviewer role is managed only from the Submission Forms
 * settings page, so it must not be offered on the user add/edit forms.
 *
 * @group mukurtu_submissions
 */
class ReviewerRoleUserFormTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * Builds a role-checkboxes element shaped like AccountForm's, so the
   * alter has something realistic to operate on without standing up the
   * whole access-gated user form.
   */
  protected function roleForm(): array {
    return [
      'account' => [
        'roles' => [
          '#type' => 'checkboxes',
          '#options' => [
            SubmissionSettingsCollectionForm::REVIEWER_ROLE => 'Submission Reviewer',
            'mukurtu_manager' => 'Mukurtu Manager',
          ],
        ],
      ],
    ];
  }

  public function testReviewerRoleRemovedFromUserForm(): void {
    $this->assertTrue(function_exists('mukurtu_submissions_form_user_form_alter'));

    $form = $this->roleForm();
    $form_state = new FormState();
    mukurtu_submissions_form_user_form_alter($form, $form_state);

    $options = $form['account']['roles']['#options'];
    $this->assertArrayNotHasKey(SubmissionSettingsCollectionForm::REVIEWER_ROLE, $options);
    $this->assertArrayHasKey('mukurtu_manager', $options);
  }

  public function testReviewerRoleRemovedFromUserRegisterForm(): void {
    $this->assertTrue(function_exists('mukurtu_submissions_form_user_register_form_alter'));

    $form = $this->roleForm();
    $form_state = new FormState();
    mukurtu_submissions_form_user_register_form_alter($form, $form_state);

    $this->assertArrayNotHasKey(
      SubmissionSettingsCollectionForm::REVIEWER_ROLE,
      $form['account']['roles']['#options']
    );
  }

  public function testAlterIsANoOpWhenNoRolesElement(): void {
    $form = ['account' => []];
    $form_state = new FormState();
    mukurtu_submissions_form_user_form_alter($form, $form_state);
    $this->assertSame(['account' => []], $form);
  }

}
