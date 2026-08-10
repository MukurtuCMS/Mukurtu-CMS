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

}
