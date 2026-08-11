<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests mukurtu_notifications_update_40055(), which opts uid 1 in to
 * "User account" category emails.
 *
 * @see mukurtu_notifications_update_40055()
 * @group mukurtu_notifications
 */
class Uid1EmailCategoryUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'options',
    'system',
    'user',
    'mukurtu_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_notifications');
    require_once $module_path . '/mukurtu_notifications.install';

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    mukurtu_notifications_create_field_notify_email_categories();
  }

  /**
   * The update hook adds 'user_account' to uid 1's existing selection
   * without disturbing other categories already selected.
   */
  public function testUpdateAddsUserAccountCategory(): void {
    $uidOne = User::create(['name' => 'admin', 'status' => 1]);
    $uidOne->save();
    $this->assertSame(1, (int) $uidOne->id());

    $uidOne->set('field_notify_email_categories', ['publishing']);
    $uidOne->save();

    mukurtu_notifications_update_40055();

    $uidOne = User::load(1);
    $categories = array_column($uidOne->get('field_notify_email_categories')->getValue(), 'value');
    $this->assertContains('user_account', $categories);
    $this->assertContains('publishing', $categories);
  }

  /**
   * Running the update hook again once 'user_account' is already present
   * is a no-op.
   */
  public function testUpdateIsIdempotent(): void {
    $uidOne = User::create(['name' => 'admin', 'status' => 1]);
    $uidOne->save();

    $uidOne->set('field_notify_email_categories', ['publishing', 'user_account']);
    $uidOne->save();

    mukurtu_notifications_update_40055();

    $uidOne = User::load(1);
    $categories = array_column($uidOne->get('field_notify_email_categories')->getValue(), 'value');
    $this->assertSame(['publishing', 'user_account'], array_values($categories));
  }

}
