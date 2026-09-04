<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests mukurtu_submissions_update_40013(): creates the
 * "mukurtu_submission_reviewer" role for existing sites (new installs get
 * it from config/install/user.role.mukurtu_submission_reviewer.yml
 * directly), backfills its per-bundle content permissions via
 * mukurtu_submissions_sync_review_permissions(), and enrolls anyone
 * already in notify_uids - see
 * SubmissionSettingsCollectionForm::syncNotifyReviewerRoles() for the
 * ongoing (post-update) grant/revoke path this backfills for existing
 * sites.
 *
 * @group mukurtu_submissions
 */
class SubmissionReviewerRoleUpdateTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * Calls the update hook directly, the same way update.php would.
   */
  protected function runUpdateHook(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    mukurtu_submissions_update_40013();
  }

  public function testCreatesRoleWithReviewerPermissions(): void {
    $this->runUpdateHook();

    $role = Role::load('mukurtu_submission_reviewer');
    $this->assertNotNull($role);
    $this->assertTrue($role->hasPermission('review mukurtu submissions'));
    $this->assertFalse($role->hasPermission('administer mukurtu submissions'));
  }

  /**
   * The media edit/delete permissions themselves are exercised via
   * mukurtu_submissions_reviewer_role_permissions()'s return value rather
   * than a real Role save/reload - Drupal core's Role::preSave() (see
   * web/core/modules/user/src/Entity/Role.php) strips any permission not
   * currently recognized by the permission registry, and this test base
   * deliberately excludes "media" (and its "image" field-type dependency
   * chain) to stay fast - a real site always has "media" installed by the
   * time mukurtu_submissions installs/updates, so that stripping never
   * happens there.
   */
  public function testReviewerRolePermissionsIncludeMediaGrants(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    $permissions = mukurtu_submissions_reviewer_role_permissions();

    $this->assertContains('review mukurtu submissions', $permissions);
    $this->assertContains('edit any image media', $permissions);
    $this->assertContains('delete any video media', $permissions);
    $this->assertNotContains('administer mukurtu submissions', $permissions);
  }

  public function testBackfillsPerBundleContentPermissions(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
    ])->save();

    $this->runUpdateHook();

    $role = Role::load('mukurtu_submission_reviewer');
    $this->assertTrue($role->hasPermission('edit any ' . static::TEST_BUNDLE . ' content'));
    $this->assertTrue($role->hasPermission('delete any ' . static::TEST_BUNDLE . ' content'));
  }

  public function testEnrollsExistingNotifyUidsMembers(): void {
    $alice = User::create(['name' => 'alice', 'status' => 1]);
    $alice->save();

    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('notify_uids', [(int) $alice->id()])
      ->save();

    $this->runUpdateHook();

    $alice = User::load($alice->id());
    $this->assertTrue($alice->hasRole('mukurtu_submission_reviewer'));
  }

  public function testRunningTwiceDoesNotDuplicateOrError(): void {
    $this->runUpdateHook();
    $this->runUpdateHook();

    $role = Role::load('mukurtu_submission_reviewer');
    $permissions = $role->getPermissions();
    $this->assertCount(1, array_filter($permissions, fn ($p) => $p === 'review mukurtu submissions'));
  }

  public function testExistingRoleWithPermissionsIsLeftUnchanged(): void {
    $role = Role::create(['id' => 'mukurtu_submission_reviewer', 'label' => 'Submission Reviewer']);
    $role->grantPermission('review mukurtu submissions');
    $role->save();

    $this->runUpdateHook();

    $role = Role::load('mukurtu_submission_reviewer');
    $this->assertTrue($role->hasPermission('review mukurtu submissions'));
  }

}
