<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\user\Entity\Role;

/**
 * Tests mukurtu_submissions_update_40012(): backfills "administer mukurtu
 * submissions" and "review mukurtu submissions" for "mukurtu_manager" (and
 * "administrator") on sites that never got them from either hook_install()
 * (skipped when $is_syncing) or mukurtu_submissions_update_40001() (never
 * runs for a site that installed the module at a schema version past
 * 40001) - see mukurtu_submissions_review_permissions().
 *
 * @group mukurtu_submissions
 */
class ManagerSubmissionsPermissionUpdateTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * Calls the update hook directly, the same way update.php would.
   */
  protected function runUpdateHook(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    mukurtu_submissions_update_40012();
  }

  public function testGrantsPermissionsToManagerRoleLackingThem(): void {
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->runUpdateHook();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('administer mukurtu submissions'));
    $this->assertTrue($role->hasPermission('review mukurtu submissions'));
  }

  public function testRunningTwiceDoesNotDuplicateOrError(): void {
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->runUpdateHook();
    $this->runUpdateHook();

    $role = Role::load('mukurtu_manager');
    $permissions = $role->getPermissions();
    $this->assertCount(1, array_filter($permissions, fn ($p) => $p === 'administer mukurtu submissions'));
  }

  public function testRoleAlreadyHoldingPermissionsIsLeftUnchanged(): void {
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager']);
    $role->grantPermission('administer mukurtu submissions');
    $role->grantPermission('review mukurtu submissions');
    $role->save();

    $this->runUpdateHook();

    $role = Role::load('mukurtu_manager');
    $this->assertTrue($role->hasPermission('administer mukurtu submissions'));
    $this->assertTrue($role->hasPermission('review mukurtu submissions'));
  }

  public function testMissingRoleIsSkippedWithoutError(): void {
    // Neither "mukurtu_manager" nor "administrator" exist in this test's
    // minimal role set - the hook must simply skip them, not error.
    $this->runUpdateHook();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
