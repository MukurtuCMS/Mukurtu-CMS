<?php

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_local_contexts_update_40005(), which finalizes the
 * confirmed-deletion thresholds (grace period and minimum consecutive
 * not_found syncs) per the Local Contexts team's confirmation, while
 * preserving any value an admin already customized away from the old
 * provisional defaults.
 *
 * @see mukurtu_local_contexts_update_40005()
 */
#[Group('mukurtu_local_contexts')]
class DeletedProjectThresholdUpdateTest extends LocalContextsTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_local_contexts');
    require_once $module_path . '/mukurtu_local_contexts.install';
  }

  /**
   * A site still at the old provisional defaults is migrated to the
   * finalized values.
   */
  public function testUpdateMigratesProvisionalDefaults(): void {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 604800)
      ->set('deleted_project_min_consecutive_failures', 3)
      ->save();

    mukurtu_local_contexts_update_40005();

    $config = $this->config('mukurtu_local_contexts.settings');
    $this->assertSame(2419200, $config->get('deleted_project_grace_period'));
    $this->assertSame(4, $config->get('deleted_project_min_consecutive_failures'));
  }

  /**
   * A site where an admin already customized the grace period keeps that
   * custom value.
   */
  public function testUpdatePreservesCustomGracePeriod(): void {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 1209600)
      ->set('deleted_project_min_consecutive_failures', 3)
      ->save();

    mukurtu_local_contexts_update_40005();

    $config = $this->config('mukurtu_local_contexts.settings');
    $this->assertSame(1209600, $config->get('deleted_project_grace_period'));
    $this->assertSame(4, $config->get('deleted_project_min_consecutive_failures'));
  }

  /**
   * A site where an admin already customized the minimum consecutive
   * failures keeps that custom value.
   */
  public function testUpdatePreservesCustomMinConsecutiveFailures(): void {
    $this->config('mukurtu_local_contexts.settings')
      ->set('deleted_project_grace_period', 604800)
      ->set('deleted_project_min_consecutive_failures', 10)
      ->save();

    mukurtu_local_contexts_update_40005();

    $config = $this->config('mukurtu_local_contexts.settings');
    $this->assertSame(2419200, $config->get('deleted_project_grace_period'));
    $this->assertSame(10, $config->get('deleted_project_min_consecutive_failures'));
  }

}
