<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\user\Entity\User;

/**
 * Tests the "Public Submissions" -> "Submission Forms" naming cleanup:
 * permission titles still resolve under their unchanged machine names, and
 * mukurtu_submissions_update_40002() only renames a service account whose
 * name still matches the old default.
 *
 * @group mukurtu_submissions
 */
class SubmissionsNamingUpdateTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * The static permissions keep their machine names; only their
   * human-readable titles changed, so admin/people/permissions and any
   * existing role grants keep working unmodified.
   */
  public function testStaticPermissionTitlesUpdated(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();

    $this->assertArrayHasKey('administer mukurtu submissions', $permissions);
    $this->assertEquals('Administer submission forms', (string) $permissions['administer mukurtu submissions']['title']);

    $this->assertArrayHasKey('review mukurtu submissions', $permissions);
    $this->assertEquals('Review submissions', (string) $permissions['review mukurtu submissions']['title']);
  }

  /**
   * Calls the update hook directly, the same way update.php would.
   */
  protected function runNamingUpdateHook(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    mukurtu_submissions_update_40002();
  }

  public function testServiceAccountRenamedWhenStillDefault(): void {
    $account = User::create(['name' => 'Public Submissions', 'status' => 0]);
    $account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $account->id())
      ->save();

    $this->runNamingUpdateHook();

    $account = User::load($account->id());
    $this->assertEquals('Submission Forms', $account->getAccountName());
  }

  public function testServiceAccountLeftAloneWhenAlreadyCustomized(): void {
    $account = User::create(['name' => 'Visitor Intake Bot', 'status' => 0]);
    $account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $account->id())
      ->save();

    $this->runNamingUpdateHook();

    $account = User::load($account->id());
    $this->assertEquals('Visitor Intake Bot', $account->getAccountName());
  }

  public function testUpdateHookNoOpWithoutServiceAccountConfigured(): void {
    // No exception, no crash, when no site has ever configured a service
    // account (e.g. a site that installed the module but never enabled any
    // bundle yet).
    $this->runNamingUpdateHook();
    $this->assertTrue(TRUE);
  }

}
