<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;

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
   * The node bundle used to create mukurtu_submission_settings entities
   * against, for tests that need one.
   */
  const TEST_BUNDLE = 'submission_test_content';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installConfig(['user']);

    NodeType::create([
      'type' => static::TEST_BUNDLE,
      'name' => 'Submission Test Content',
    ])->save();

    // mukurtu_submissions ships this as default config
    // (config/install/core.entity_form_mode.node.submission.yml), installed
    // automatically on a real site the moment the module itself is
    // installed. This base class deliberately doesn't do a full
    // installConfig(['mukurtu_submissions']) - that would also pull in the
    // digital_heritage settings entity and the message/view config that
    // depend on modules excluded above - so the one piece of shared,
    // non-optional config that SubmissionFormDisplayManager's
    // getFormDisplay('submission') calls always need is created directly
    // here instead, same as NodeType above.
    if (!EntityFormMode::load('node.submission')) {
      EntityFormMode::create([
        'id' => 'node.submission',
        'label' => 'Submission',
        'targetEntityType' => 'node',
      ])->save();
    }
  }

}
