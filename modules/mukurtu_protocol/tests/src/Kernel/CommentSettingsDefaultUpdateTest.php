<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_protocol_update_40044(), which ships an explicit
 * mukurtu_protocol.comment_settings default: comments off, approval
 * required, visitor email required (#1761).
 *
 * @see mukurtu_protocol_update_40044()
 */
#[Group('mukurtu_protocol')]
class CommentSettingsDefaultUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    require_once $module_path . '/mukurtu_protocol.install';
  }

  /**
   * The update hook installs the new default when the site never saved this
   * form (config doesn't exist yet).
   */
  public function testUpdateInstallsDefaultWhenUnset(): void {
    $this->assertTrue(\Drupal::config('mukurtu_protocol.comment_settings')->isNew());

    mukurtu_protocol_update_40044();

    $config = \Drupal::config('mukurtu_protocol.comment_settings');
    $this->assertFalse($config->get('site_comments_enabled'));
    $this->assertTrue($config->get('site_comments_require_approval'));
    $this->assertTrue($config->get('anonymous_comments_require_email'));
  }

  /**
   * The update hook leaves an admin's own saved choice alone.
   */
  public function testUpdateLeavesExistingChoiceAlone(): void {
    \Drupal::configFactory()->getEditable('mukurtu_protocol.comment_settings')
      ->set('site_comments_enabled', TRUE)
      ->set('site_comments_require_approval', FALSE)
      ->set('anonymous_comments_require_email', FALSE)
      ->save();

    mukurtu_protocol_update_40044();

    $config = \Drupal::config('mukurtu_protocol.comment_settings');
    $this->assertTrue($config->get('site_comments_enabled'));
    $this->assertFalse($config->get('site_comments_require_approval'));
    $this->assertFalse($config->get('anonymous_comments_require_email'));
  }

}
