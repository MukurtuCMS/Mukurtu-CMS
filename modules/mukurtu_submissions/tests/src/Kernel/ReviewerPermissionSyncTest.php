<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that "edit any {bundle} content" / "delete any {bundle} content"
 * are granted dynamically to reviewer roles when a settings entity for
 * that bundle is enabled - previously this only ever happened for
 * digital_heritage, hardcoded at install time.
 */
#[Group('mukurtu_submissions')]
class ReviewerPermissionSyncTest extends MukurtuSubmissionsKernelTestBase {

  protected function createReviewerRole(): Role {
    $role = Role::create(['id' => 'reviewer', 'label' => 'Reviewer']);
    $role->grantPermission('review mukurtu submissions');
    $role->save();
    return $role;
  }

  public function testEnablingSettingsGrantsReviewerRolePermissions(): void {
    $this->createReviewerRole();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
    ])->save();

    $role = Role::load('reviewer');
    $this->assertTrue($role->hasPermission('edit any ' . static::TEST_BUNDLE . ' content'));
    $this->assertTrue($role->hasPermission('delete any ' . static::TEST_BUNDLE . ' content'));
  }

  public function testDisabledSettingsDoNotGrantPermissions(): void {
    $this->createReviewerRole();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => FALSE,
    ])->save();

    $role = Role::load('reviewer');
    $this->assertFalse($role->hasPermission('edit any ' . static::TEST_BUNDLE . ' content'));
    $this->assertFalse($role->hasPermission('delete any ' . static::TEST_BUNDLE . ' content'));
  }

  public function testDisablingAfterEnablingDoesNotRevoke(): void {
    $this->createReviewerRole();

    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
    ]);
    $settings->save();

    $settings->set('status', FALSE);
    $settings->save();

    $role = Role::load('reviewer');
    $this->assertTrue($role->hasPermission('edit any ' . static::TEST_BUNDLE . ' content'));
    $this->assertTrue($role->hasPermission('delete any ' . static::TEST_BUNDLE . ' content'));
  }

  public function testUpdateHookBackfillsExistingEntitiesWithoutDoubleGranting(): void {
    $this->createReviewerRole();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
    ])->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    // Calling it twice must be a safe no-op the second time, not an error
    // or a duplicate grant.
    mukurtu_submissions_update_40003();
    mukurtu_submissions_update_40003();

    $role = Role::load('reviewer');
    $permissions = $role->getPermissions();
    $this->assertCount(1, array_filter($permissions, fn ($p) => $p === 'edit any ' . static::TEST_BUNDLE . ' content'));
  }

}
