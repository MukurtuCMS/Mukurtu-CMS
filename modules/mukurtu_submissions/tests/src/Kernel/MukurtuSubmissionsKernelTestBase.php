<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Base class for mukurtu_submissions kernel tests.
 *
 * Deliberately excludes mukurtu_notifications, message, message_notify,
 * message_subscribe, mukurtu_media, and mukurtu_protocol - none of
 * SubmissionSettings/SubmissionPermissions/SubmissionAccessCheck/the
 * module's update hooks touch those directly, and KernelTestBase's
 * $modules list never resolves or installs hard dependencies (no
 * hook_install() runs), so leaving them out is safe rather than a gap.
 */
abstract class MukurtuSubmissionsKernelTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'options',
    'path_alias',
    'node',
    'views',
    'mukurtu_submissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installConfig(['user']);
  }

}
