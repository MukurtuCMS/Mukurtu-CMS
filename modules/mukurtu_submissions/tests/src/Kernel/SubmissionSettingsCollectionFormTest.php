<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_submissions\Form\SubmissionSettingsCollectionForm;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Submission Forms" collection page, which combines the
 * per-content-type submission settings list with the reviewer-notification
 * settings on one page/route.
 */
#[Group('mukurtu_submissions')]
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
   * Sets the starting notify_uids list directly in config (the reviewers
   * table is built from this).
   */
  protected function seedNotifyUids(array $uids): void {
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_uids', array_map('intval', $uids))
      ->save();
  }

  /**
   * Submits the form: the single "add" pick plus the set of table rows
   * whose "remove" box is ticked. Calls submitForm() directly, as the rest
   * of this suite does, rather than re-exercising entity_autocomplete's
   * own string-parsing pipeline.
   */
  protected function submitReviewers(?int $add = NULL, array $remove = [], string $notify_emails = ''): void {
    $rows = [];
    foreach ($remove as $uid) {
      $rows[(int) $uid] = ['remove' => 1];
    }
    $values = [
      'reviewers' => $rows,
      'notify_emails' => $notify_emails,
    ];
    if ($add !== NULL) {
      $values['add_reviewer'] = (string) $add;
    }
    $form = [];
    $this->getFormObject()->submitForm($form, (new FormState())->setValues($values));
  }

  /**
   * Runs validateForm() then, only if that added no errors, submitForm() -
   * needed for notify_emails, which is only checked in validateForm().
   */
  protected function runNotifySettingsForm(string $notify_emails_text): FormState {
    $form_state = (new FormState())->setValues([
      'reviewers' => [],
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

  public function testBuildFormIncludesTheReviewerControlsAndSettingsList(): void {
    $form = $this->getFormObject()->buildForm([], new FormState());

    $this->assertArrayHasKey('notifications', $form);
    $this->assertArrayHasKey('reviewers', $form['notifications']);
    $this->assertArrayHasKey('add_reviewer', $form['notifications']);
    $this->assertArrayHasKey('list', $form);
    $this->assertNotEmpty($form['list']);
  }

  public function testReviewerTableRowsAConfiguredUser(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();
    $this->seedNotifyUids([$alice->id()]);

    $form = $this->getFormObject()->buildForm([], new FormState());
    $row = $form['notifications']['reviewers'][(int) $alice->id()];

    $this->assertSame('alice', $row['name']['#plain_text']);
    $this->assertSame('checkbox', $row['remove']['#type']);
  }

  public function testReviewerTableEmptyState(): void {
    $form = $this->getFormObject()->buildForm([], new FormState());

    $this->assertSame(
      'No additional reviewers have been added yet.',
      (string) $form['notifications']['reviewers']['#empty']
    );
  }

  public function testAddingAReviewerSavesTheUidAndGrantsTheRole(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitReviewers(add: (int) $alice->id());

    $this->assertSame([(int) $alice->id()], \Drupal::config('mukurtu_submissions.settings')->get('notify_uids'));
    $this->assertTrue(User::load($alice->id())->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
  }

  public function testAddingASecondReviewerKeepsTheFirst(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();
    $bob = User::create(['name' => 'bob', 'status' => 1]);
    $bob->save();

    $this->submitReviewers(add: (int) $alice->id());
    $this->submitReviewers(add: (int) $bob->id());

    $this->assertEqualsCanonicalizing(
      [(int) $alice->id(), (int) $bob->id()],
      \Drupal::config('mukurtu_submissions.settings')->get('notify_uids')
    );
  }

  public function testAddedUidIsMergedByGetReviewerUids(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitReviewers(add: (int) $alice->id());

    $this->assertContains((int) $alice->id(), mukurtu_submissions_get_reviewer_uids());
  }

  public function testRemovingAReviewerRevokesOnlyTheReviewerRole(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->addRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE);
    $alice->addRole('other_role_marker');
    $alice->save();
    $this->seedNotifyUids([$alice->id()]);

    $this->submitReviewers(remove: [(int) $alice->id()]);

    $alice = User::load($alice->id());
    $this->assertSame([], \Drupal::config('mukurtu_submissions.settings')->get('notify_uids'));
    $this->assertFalse($alice->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
    $this->assertTrue($alice->hasRole('other_role_marker'));
  }

  public function testAddAndRemoveInOneSubmit(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();
    $bob = User::create(['name' => 'bob', 'status' => 1]);
    $bob->save();
    $this->seedNotifyUids([$alice->id()]);

    $this->submitReviewers(add: (int) $bob->id(), remove: [(int) $alice->id()]);

    $this->assertSame([(int) $bob->id()], \Drupal::config('mukurtu_submissions.settings')->get('notify_uids'));
    $this->assertFalse(User::load($alice->id())->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
    $this->assertTrue(User::load($bob->id())->hasRole(SubmissionSettingsCollectionForm::REVIEWER_ROLE));
  }

  public function testAddingAnAlreadyListedReviewerIsANoOp(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitReviewers(add: (int) $alice->id());
    $this->submitReviewers(add: (int) $alice->id());

    $this->assertSame([(int) $alice->id()], \Drupal::config('mukurtu_submissions.settings')->get('notify_uids'));
    $this->assertCount(1, array_filter(
      User::load($alice->id())->getRoles(),
      fn ($r) => $r === SubmissionSettingsCollectionForm::REVIEWER_ROLE
    ));
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

  public function testStatusMessageShownOnlyWhenAReviewerIsAdded(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    $this->submitReviewers(add: (int) $alice->id());
    $this->assertTrue($this->hasReviewerGrantMessage());

    \Drupal::messenger()->deleteAll();

    $this->submitReviewers();
    $this->assertFalse($this->hasReviewerGrantMessage());
  }

}
