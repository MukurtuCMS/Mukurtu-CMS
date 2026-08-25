<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mukurtu_submissions\Access\SubmissionAccessCheck;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests SubmissionAccessCheck::access() directly against every branch -
 * none of this module's other kernel tests exercise this class at all,
 * they only cover the role-permission layer one step removed from it
 * (e.g. ReviewerPermissionSyncTest, EntityBrowserPermissionSyncTest).
 *
 * @group mukurtu_submissions
 */
class SubmissionAccessCheckTest extends MukurtuSubmissionsKernelTestBase {

  const PERMISSION = 'submit node ' . self::TEST_BUNDLE . ' content';

  protected function accessCheck(): SubmissionAccessCheck {
    return new SubmissionAccessCheck();
  }

  /**
   * The first $this->createUser() call in any kernel test becomes uid 1,
   * which Drupal treats as the superuser - bypassing every permission
   * check regardless of role, making it useless as an "authenticated user
   * without permission X" test subject. Burns uid 1 on a throwaway user
   * first so the returned user is a genuine, permission-respecting uid 2+.
   */
  protected function createNonAdminUser(): UserInterface {
    $this->createUser();
    return $this->createUser();
  }

  public function testNoSettingsEntityIsForbidden(): void {
    $result = $this->accessCheck()->access(new AnonymousUserSession(), 'node', static::TEST_BUNDLE);
    $this->assertFalse($result->isAllowed());
  }

  public function testDisabledSettingsIsForbidden(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => FALSE,
      'access_level' => 'anonymous',
    ])->save();

    $result = $this->accessCheck()->access(new AnonymousUserSession(), 'node', static::TEST_BUNDLE);
    $this->assertFalse($result->isAllowed());
  }

  public function testEnabledSettingsWithoutSubmissionDisplayIsForbidden(): void {
    // Deliberately no getFormDisplay()->save() call - the "submission" form
    // display for this bundle has never been saved as config, matching a
    // settings entity created but never actually configured with fields.
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();

    $anonymous = new AnonymousUserSession();
    Role::load('anonymous')->grantPermission(static::PERMISSION)->save();

    $result = $this->accessCheck()->access($anonymous, 'node', static::TEST_BUNDLE);
    $this->assertFalse($result->isAllowed(), 'Even with the permission granted, no saved "submission" form display must still deny access.');
  }

  protected function saveSubmissionFormDisplay(): void {
    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', static::TEST_BUNDLE, 'submission')
      ->save();
  }

  public function testEnabledSettingsWithDisplayButNoPermissionIsForbidden(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();
    $this->saveSubmissionFormDisplay();

    // mukurtu_submissions_sync_submission_permission() (fired by the insert
    // hook above, since status=TRUE and access_level=anonymous) already
    // auto-grants this permission to anonymous - explicitly revoking it
    // simulates a site where that grant was manually undone, the one way
    // "enabled settings + saved display + no permission" can actually
    // occur, since SubmissionAccessCheck's own permission check doesn't
    // care why the account lacks it.
    Role::load('anonymous')->revokePermission(static::PERMISSION)->save();

    $result = $this->accessCheck()->access(new AnonymousUserSession(), 'node', static::TEST_BUNDLE);
    $this->assertFalse($result->isAllowed());
  }

  public function testEnabledSettingsWithDisplayAndPermissionIsAllowedForAnonymous(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();
    $this->saveSubmissionFormDisplay();

    $anonymous = new AnonymousUserSession();
    Role::load('anonymous')->grantPermission(static::PERMISSION)->save();

    $result = $this->accessCheck()->access($anonymous, 'node', static::TEST_BUNDLE);
    $this->assertTrue($result->isAllowed());
  }

  public function testAuthenticatedWithoutPermissionIsForbidden(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'authenticated',
    ])->save();
    $this->saveSubmissionFormDisplay();

    // Same auto-grant as testEnabledSettingsWithDisplayButNoPermissionIsForbidden
    // above - undo it to actually exercise the "lacks permission" branch.
    Role::load('authenticated')->revokePermission(static::PERMISSION)->save();

    $account = $this->createNonAdminUser();
    $result = $this->accessCheck()->access($account, 'node', static::TEST_BUNDLE);
    $this->assertFalse($result->isAllowed());
  }

  public function testAuthenticatedWithPermissionIsAllowed(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'authenticated',
    ])->save();
    $this->saveSubmissionFormDisplay();
    Role::load('authenticated')->grantPermission(static::PERMISSION)->save();

    $account = $this->createNonAdminUser();
    $result = $this->accessCheck()->access($account, 'node', static::TEST_BUNDLE);
    $this->assertTrue($result->isAllowed());
  }

}
