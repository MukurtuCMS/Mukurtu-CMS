<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the safety-fallback execute() path on MukurtuBlockUserAction.
 *
 * The "Block or delete" bulk action always routes through
 * MukurtuUserCancelConfirmForm via confirm_form_route_name, so execute()
 * is never actually called from the Views Bulk Operations UI. It's kept as
 * a defensive fallback in case the action is ever invoked directly (e.g.
 * programmatically); this test exercises that fallback path.
 */
#[Group('mukurtu_protocol')]
class MukurtuBlockUserActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);

    FieldStorageConfig::create([
      'field_name' => 'field_pending',
      'entity_type' => 'user',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_pending',
      'entity_type' => 'user',
      'bundle' => 'user',
    ])->save();

    Role::create(['id' => 'administrator', 'label' => 'Administrator'])
      ->grantPermission('administer users')
      ->save();

    $admin = User::create(['name' => 'admin']);
    $admin->addRole('administrator');
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);
  }

  /**
   * Creates a saved target user with the given status/pending values.
   */
  protected function createTargetUser(bool $active, bool $pending): User {
    $user = User::create([
      'name' => $this->randomMachineName(),
      'status' => $active,
    ]);
    $user->set('field_pending', $pending);
    $user->save();
    return $user;
  }

  /**
   * An active user is blocked and cleared of pending status.
   */
  public function testExecuteBlocksActiveUser(): void {
    $target = $this->createTargetUser(active: TRUE, pending: FALSE);

    $action = \Drupal::service('plugin.manager.action')->createInstance('mukurtu_block_user_action');
    $action->execute($target);

    $target = User::load($target->id());
    $this->assertFalse((bool) $target->status->value);
    $this->assertEquals(0, $target->get('field_pending')->value);
  }

  /**
   * An already-blocked-but-pending user is explicitly marked as blocked.
   */
  public function testExecuteClearsPendingOnBlockedUser(): void {
    $target = $this->createTargetUser(active: FALSE, pending: TRUE);

    $action = \Drupal::service('plugin.manager.action')->createInstance('mukurtu_block_user_action');
    $action->execute($target);

    $target = User::load($target->id());
    $this->assertFalse((bool) $target->status->value);
    $this->assertEquals(0, $target->get('field_pending')->value);
  }

}
