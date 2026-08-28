<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\user\Entity\User;

/**
 * Tests exporting a user's account status as a single Active/Blocked/
 * Pending value.
 */
class CsvExportAccountStatusTest extends CsvExportFieldTestBase {

  /**
   * Installs the mukurtu_core field_pending field on the user entity,
   * mirroring ImportUserAccountTest::installFieldPending().
   */
  protected function installFieldPending(): void {
    if (!FieldStorageConfig::loadByName('user', 'field_pending')) {
      FieldStorageConfig::create([
        'field_name' => 'field_pending',
        'entity_type' => 'user',
        'type' => 'boolean',
      ])->save();
    }
    if (!FieldConfig::loadByName('user', 'user', 'field_pending')) {
      FieldConfig::create([
        'field_name' => 'field_pending',
        'entity_type' => 'user',
        'bundle' => 'user',
        'default_value' => [['value' => 1]],
      ])->save();
    }
  }

  /**
   * Creates a user distinct from $this->currentUser (the session's own
   * account) with the given status/field_pending values.
   *
   * mukurtu_core_entity_presave() deliberately forces status back to
   * active for a save that would block the currently logged-in user's own
   * account, so exercising Blocked/Pending export requires a separate,
   * non-session user.
   */
  protected function createUserWithStatus(int $status, int $pending): User {
    $this->installFieldPending();
    $user = User::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => $status,
      'field_pending' => $pending,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Test exporting an active user.
   */
  public function testActiveAccountExportsActive(): void {
    $user = $this->createUserWithStatus(1, 0);

    $event = new EntityFieldExportEvent('csv', $user, 'account_status', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals(['Active'], $event->getValue());
  }

  /**
   * Test exporting a blocked user.
   */
  public function testBlockedAccountExportsBlocked(): void {
    $user = $this->createUserWithStatus(0, 0);

    $event = new EntityFieldExportEvent('csv', $user, 'account_status', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals(['Blocked'], $event->getValue());
  }

  /**
   * Test exporting a pending user.
   */
  public function testPendingAccountExportsPending(): void {
    $user = $this->createUserWithStatus(0, 1);

    $event = new EntityFieldExportEvent('csv', $user, 'account_status', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals(['Pending'], $event->getValue());
  }

}
