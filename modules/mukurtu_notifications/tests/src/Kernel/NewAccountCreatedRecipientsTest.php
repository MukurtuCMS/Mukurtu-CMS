<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests mukurtu_notifications_notify_new_account_created()'s recipient list.
 *
 * Regression test: a new user created with the mukurtu_manager role already
 * qualified as a recipient of their own "new account created" notification,
 * since the manager-role lookup runs after the account is saved. See
 * mukurtu_notifications_notify_new_account_created().
 *
 * @group mukurtu_notifications
 */
class NewAccountCreatedRecipientsTest extends KernelTestBase {

  /**
   * Disabled: message_ui's MessageUIFieldDisplayManagerService writes a raw
   * '#group' key into entity_form_display config whenever any MessageTemplate
   * is saved, which never validates against config schema regardless of
   * which modules are enabled. Pre-existing, unrelated to this test; see
   * also the already-broken NotificationsFeedAccessTest in this module.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'field',
    'field_group',
    'file',
    'filter',
    'flag',
    'image',
    'layout_builder',
    'media',
    'message',
    'message_notify',
    'message_subscribe',
    'message_subscribe_email',
    'message_ui',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'mukurtu_collection',
    'mukurtu_core',
    'mukurtu_notifications',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_notifications');
    require_once $module_path . '/mukurtu_notifications.install';

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('system', ['sequences']);

    $this->installConfig(['message']);
    mukurtu_notifications_create_field_notify_email_categories();

    // Create only the template/fields this test needs directly via the
    // Field API and MessageTemplate, rather than
    // installConfig(['mukurtu_notifications']), which would also install
    // other templates' entity_form_display config -- at least one of which
    // fails config schema validation under KernelTestBase due to a missing
    // field_group dependency (a pre-existing, unrelated test-infra issue in
    // this module).
    MessageTemplate::create([
      'template' => 'mukurtu_new_user_account_created',
      'label' => 'New user account created',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_title',
      'entity_type' => 'message',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_title',
      'entity_type' => 'message',
      'bundle' => 'mukurtu_new_user_account_created',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_user',
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'user'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_user',
      'entity_type' => 'message',
      'bundle' => 'mukurtu_new_user_account_created',
    ])->save();

    // uid 1 is created implicitly and bypasses permission checks -- burn it
    // on a throwaway user first so the real test users below aren't
    // accidentally uid 1.
    User::create(['name' => 'throwaway'])->save();

    Role::create(['id' => 'authenticated', 'label' => 'Authenticated'])->save();
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();
  }

  /**
   * The newly created user is excluded from their own notification, even
   * when they already carry the mukurtu_manager role.
   */
  public function testNewManagerIsNotEmailedAboutOwnCreation(): void {
    $existingManager = User::create(['name' => 'existing_manager', 'status' => 1]);
    $existingManager->addRole('mukurtu_manager');
    $existingManager->save();

    $newUser = User::create(['name' => 'new_manager', 'status' => 1]);
    $newUser->addRole('mukurtu_manager');
    $newUser->save();

    mukurtu_notifications_notify_new_account_created($newUser, []);

    $messages = \Drupal::entityTypeManager()
      ->getStorage('message')
      ->loadByProperties(['template' => 'mukurtu_new_user_account_created']);
    $recipientUids = array_map(static fn (Message $message) => (int) $message->getOwnerId(), $messages);

    $this->assertNotContains((int) $newUser->id(), $recipientUids, 'The new user should not be a recipient of their own account-creation notification.');
    $this->assertContains((int) $existingManager->id(), $recipientUids, 'An existing manager should still be notified.');
  }

  /**
   * Explicitly selected notify_uids that don't overlap with the new user
   * still receive the notification.
   */
  public function testExplicitNotifyUidsStillNotified(): void {
    $selected = User::create(['name' => 'selected_notifyee', 'status' => 1]);
    $selected->save();

    $newUser = User::create(['name' => 'new_user', 'status' => 1]);
    $newUser->save();

    mukurtu_notifications_notify_new_account_created($newUser, [(int) $selected->id()]);

    $messages = \Drupal::entityTypeManager()
      ->getStorage('message')
      ->loadByProperties(['template' => 'mukurtu_new_user_account_created']);
    $recipientUids = array_map(static fn (Message $message) => (int) $message->getOwnerId(), $messages);

    $this->assertContains((int) $selected->id(), $recipientUids);
  }

}
