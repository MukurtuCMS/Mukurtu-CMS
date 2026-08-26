<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_submissions\Form\SubmissionSettingsCollectionForm;
use Drupal\user\Entity\User;

/**
 * Tests the "Submission Forms" collection page, which now combines the
 * per-content-type submission settings list with the notify_uids setting
 * on one page/route instead of two.
 *
 * @group mukurtu_submissions
 */
class SubmissionSettingsCollectionFormTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  protected function getFormObject(): SubmissionSettingsCollectionForm {
    return \Drupal::classResolver(SubmissionSettingsCollectionForm::class);
  }

  /**
   * Calls submitForm() directly (see SubmissionNotifySettingsFormTest's
   * removed equivalent, and ReviewStateFormTest, for why this codebase
   * prefers testing submitForm() logic directly over re-exercising
   * entity_autocomplete's own core string-parsing pipeline).
   */
  protected function submitNotifyUids(array $uids): void {
    $value = array_map(fn(int $uid) => ['target_id' => $uid], $uids);
    $form_state = (new FormState())->setValues(['notify_uids' => $value]);

    $form = [];
    $this->getFormObject()->submitForm($form, $form_state);
  }

  /**
   * Runs validateForm() then, only if that added no errors, submitForm() -
   * needed for notify_emails, which is only checked in validateForm().
   */
  protected function runNotifySettingsForm(string $notify_emails_text): FormState {
    $form_state = (new FormState())->setValues([
      'notify_uids' => [],
      'notify_emails' => $notify_emails_text,
    ]);

    $form_object = $this->getFormObject();
    $form = [];
    $form_object->validateForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $form_object->submitForm($form, $form_state);
    }
    return $form_state;
  }

  public function testBuildFormIncludesTheEmbeddedSettingsList(): void {
    $form_state = new FormState();
    $form = $this->getFormObject()->buildForm([], $form_state);

    $this->assertArrayHasKey('notifications', $form);
    $this->assertArrayHasKey('notify_uids', $form['notifications']);
    $this->assertArrayHasKey('list', $form);
    $this->assertNotEmpty($form['list']);
  }

  public function testSubmittingSavesNotifyUids(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();
    $bob = User::create(['name' => 'bob', 'status' => 1]);
    $bob->save();

    $this->submitNotifyUids([(int) $alice->id(), (int) $bob->id()]);

    $notify_uids = \Drupal::config('mukurtu_submissions.settings')->get('notify_uids');
    $this->assertEqualsCanonicalizing([(int) $alice->id(), (int) $bob->id()], $notify_uids);
  }

  public function testSavedUidsAreMergedByGetReviewerUids(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitNotifyUids([(int) $alice->id()]);

    $reviewer_uids = mukurtu_submissions_get_reviewer_uids();
    $this->assertContains((int) $alice->id(), $reviewer_uids);
  }

  public function testEmptySubmissionClearsNotifyUids(): void {
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_uids', [999])
      ->save();

    $this->submitNotifyUids([]);

    $notify_uids = \Drupal::config('mukurtu_submissions.settings')->get('notify_uids');
    $this->assertSame([], $notify_uids);
  }

  public function testValidNotifyEmailsAreSaved(): void {
    $form_state = $this->runNotifySettingsForm("one@example.com\ntwo@example.com");

    $this->assertEmpty($form_state->getErrors());
    $this->assertEqualsCanonicalizing(
      ['one@example.com', 'two@example.com'],
      \Drupal::config('mukurtu_submissions.settings')->get('notify_emails')
    );
  }

  public function testBlankLinesAndWhitespaceAreIgnored(): void {
    $form_state = $this->runNotifySettingsForm("  one@example.com  \n\n\ntwo@example.com\n");

    $this->assertEmpty($form_state->getErrors());
    $this->assertEqualsCanonicalizing(
      ['one@example.com', 'two@example.com'],
      \Drupal::config('mukurtu_submissions.settings')->get('notify_emails')
    );
  }

  public function testInvalidNotifyEmailBlocksSaveWithError(): void {
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_emails', ['already-there@example.com'])
      ->save();

    $form_state = $this->runNotifySettingsForm("one@example.com\nnot-an-email");

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('not-an-email', (string) reset($errors));
    // The invalid submission must not have overwritten the prior config.
    $this->assertSame(['already-there@example.com'], \Drupal::config('mukurtu_submissions.settings')->get('notify_emails'));
  }

  public function testEmptySubmissionClearsNotifyEmails(): void {
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_emails', ['old@example.com'])
      ->save();

    $form_state = $this->runNotifySettingsForm('');

    $this->assertEmpty($form_state->getErrors());
    $this->assertSame([], \Drupal::config('mukurtu_submissions.settings')->get('notify_emails'));
  }

  public function testAddingNotifyUidGrantsReviewerRole(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitNotifyUids([(int) $alice->id()]);

    $alice = User::load($alice->id());
    $this->assertTrue($alice->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
  }

  public function testRemovingNotifyUidRevokesOnlyTheReviewerRole(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->addRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE);
    $alice->addRole('other_role_marker');
    $alice->save();

    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_uids', [(int) $alice->id()])
      ->save();

    $this->submitNotifyUids([]);

    $alice = User::load($alice->id());
    $this->assertFalse($alice->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
    $this->assertTrue($alice->hasRole('other_role_marker'));
  }

  public function testResubmittingSameListDoesNotDuplicateRoleOrError(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitNotifyUids([(int) $alice->id()]);
    $this->submitNotifyUids([(int) $alice->id()]);

    $alice = User::load($alice->id());
    $this->assertCount(1, array_filter($alice->getRoles(), fn ($r) => $r === SubmissionSettingsCollectionForm::REVIEWER_ROLE));
  }

  /**
   * ConfigFormBase::submitForm() always adds its own generic "configuration
   * saved" status message, so this checks for the specific reviewer-grant
   * message rather than "any status message present".
   */
  protected function hasReviewerGrantMessage(): bool {
    foreach (\Drupal::messenger()->all()['status'] ?? [] as $message) {
      if (str_contains((string) $message, 'Submission Reviewer role')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function testStatusMessageShownOnlyWhenUidsAdded(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitNotifyUids([(int) $alice->id()]);
    $this->assertTrue($this->hasReviewerGrantMessage());

    \Drupal::messenger()->deleteAll();

    $this->submitNotifyUids([(int) $alice->id()]);
    $this->assertFalse($this->hasReviewerGrantMessage());
  }

}
