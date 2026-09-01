<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_notifications\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the field_notify_frequency 'none' option and its update hook.
 *
 * "N/A" used to be Drupal's synthetic empty-option placeholder for this
 * optional list field, which always saved as an empty value and could
 * never actually suppress email. mukurtu_notifications_update_40057() adds
 * a real 'none' allowed value and makes the field required so that
 * placeholder can no longer appear.
 *
 * @see mukurtu_notifications_notification_frequency_allowed_values()
 * @see _mukurtu_notifications_user_wants_email()
 * @see mukurtu_notifications_update_40057()
 * @group mukurtu_notifications
 */
class NotifyFrequencyTest extends KernelTestBase {

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

    mukurtu_notifications_create_field_notify_frequency();

    // uid 1 bypasses permission checks and is created implicitly; burn it on
    // a throwaway user so the real test users below aren't accidentally
    // uid 1. Status must be active -- mukurtu_notifications_user_insert()
    // otherwise treats an inactive save as a pending-approval registration
    // and calls into mukurtu_core, which isn't enabled in this test.
    User::create(['name' => 'throwaway', 'status' => 1])->save();
  }

  /**
   * 'none' is a real allowed value alongside the existing three.
   */
  public function testNoneIsAnAllowedValue(): void {
    $definition = FieldStorageConfig::loadByName('user', 'field_notify_frequency');
    $values = mukurtu_notifications_notification_frequency_allowed_values($definition);

    $this->assertSame([
      'immediate' => 'Immediate',
      'daily' => 'Daily',
      'weekly' => 'Weekly',
      'none' => 'Do not send emails',
    ], $values);
  }

  /**
   * A user who chose 'none' is never emailed for an ordinary notification.
   */
  public function testNoneSuppressesEmail(): void {
    $user = User::create(['name' => 'opted_out', 'status' => 1]);
    $user->set('field_notify_frequency', 'none');
    $user->save();

    $this->assertFalse(_mukurtu_notifications_user_wants_email((int) $user->id(), 'mukurtu_new_user_account_created'));
  }

  /**
   * The pending-approval notice to managers is never opt-out-able, even for
   * a user who chose 'none'.
   */
  public function testNoneDoesNotSuppressMandatoryRegistrationNotice(): void {
    $user = User::create(['name' => 'opted_out_manager', 'status' => 1]);
    $user->set('field_notify_frequency', 'none');
    $user->save();

    $this->assertTrue(_mukurtu_notifications_user_wants_email((int) $user->id(), 'mukurtu_new_user_registration'));
  }

  /**
   * A user with any other frequency falls through to the normal category
   * check, which defaults to TRUE when field_notify_email_categories isn't
   * present.
   */
  public function testImmediateFallsThroughToCategoryCheck(): void {
    $user = User::create(['name' => 'immediate_user', 'status' => 1]);
    $user->set('field_notify_frequency', 'immediate');
    $user->save();

    $this->assertTrue(_mukurtu_notifications_user_wants_email((int) $user->id(), 'mukurtu_new_user_account_created'));
  }

  /**
   * The field-creation function itself backfills empty values too, not
   * just the update hook.
   *
   * This is what actually protects uid 1 on a fresh install: it's commonly
   * created before mukurtu_notifications_install() runs, so its field
   * value would otherwise stay empty even though FieldConfig's
   * default_value is 'immediate' -- that default only applies to entities
   * created after the field already exists.
   */
  public function testFieldCreationBackfillsExistingEmptyValues(): void {
    $existingAdmin = User::create(['name' => 'existing_admin', 'status' => 1]);
    $existingAdmin->set('field_notify_frequency', NULL);
    $existingAdmin->save();
    $this->assertTrue($existingAdmin->get('field_notify_frequency')->isEmpty());

    mukurtu_notifications_create_field_notify_frequency();

    $existingAdmin = User::load($existingAdmin->id());
    $this->assertSame('immediate', $existingAdmin->get('field_notify_frequency')->value);
  }

  /**
   * The update hook backfills only users with an empty stored value.
   *
   * A brand new user picks up field_notify_frequency's 'immediate' default
   * value automatically, so the empty-value scenario this update hook
   * exists for -- an account that predates the field, or one saved before
   * "N/A" (the synthetic empty-option placeholder) stopped being offered --
   * has to be forced explicitly here.
   */
  public function testUpdateBackfillsEmptyValuesToImmediate(): void {
    $untouched = User::create(['name' => 'untouched', 'status' => 1]);
    $untouched->set('field_notify_frequency', NULL);
    $untouched->save();
    $this->assertTrue($untouched->get('field_notify_frequency')->isEmpty());

    $optedOut = User::create(['name' => 'already_opted_out', 'status' => 1]);
    $optedOut->set('field_notify_frequency', 'none');
    $optedOut->save();

    mukurtu_notifications_update_40057();

    $untouched = User::load($untouched->id());
    $this->assertSame('immediate', $untouched->get('field_notify_frequency')->value);

    $optedOut = User::load($optedOut->id());
    $this->assertSame('none', $optedOut->get('field_notify_frequency')->value);
  }

  /**
   * The update hook marks the field required.
   *
   * A fresh call to mukurtu_notifications_create_field_notify_frequency()
   * already creates the field as required, so an existing pre-update site
   * -- the scenario this hook exists for -- is simulated by explicitly
   * reverting that here first.
   */
  public function testUpdateMakesFieldRequired(): void {
    $field = FieldConfig::loadByName('user', 'user', 'field_notify_frequency');
    $field->setRequired(FALSE)->save();

    mukurtu_notifications_update_40057();

    $field = FieldConfig::loadByName('user', 'user', 'field_notify_frequency');
    $this->assertTrue($field->isRequired());
  }

  /**
   * Running the update hook again once required and backfilled is a no-op.
   */
  public function testUpdateIsIdempotent(): void {
    mukurtu_notifications_update_40057();
    mukurtu_notifications_update_40057();

    $field = FieldConfig::loadByName('user', 'user', 'field_notify_frequency');
    $this->assertTrue($field->isRequired());
  }

}
