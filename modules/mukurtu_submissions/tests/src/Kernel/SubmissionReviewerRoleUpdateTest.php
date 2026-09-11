<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the permission set behind the "mukurtu_submission_reviewer" role.
 *
 * The role itself ships as config/install/user.role.mukurtu_submission_reviewer
 * .yml, and ShippedRolePermissionsTest asserts what that file grants.
 * mukurtu_submissions_reviewer_role_permissions() is the computed set the
 * shipped file must not drift from, so it is asserted here directly.
 *
 * @see SubmissionSettingsCollectionForm::syncNotifyReviewerRoles()
 *
 * @group mukurtu_submissions
 */
class SubmissionReviewerRoleUpdateTest extends MukurtuSubmissionsKernelTestBase {
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
}
