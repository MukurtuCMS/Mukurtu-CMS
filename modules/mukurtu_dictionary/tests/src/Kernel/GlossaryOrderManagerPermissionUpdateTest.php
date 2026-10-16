<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40047().
 *
 * During a profile install mukurtu_dictionary installs before the profile's
 * mukurtu_manager role config exists, so mukurtu_dictionary_install() never
 * granted the glossary order permission. The update hook grants it on
 * existing sites. mukurtu_dictionary is enabled (without its real install)
 * only so the permission exists, since Role::save() rejects unknown
 * permissions.
 *
 * @see mukurtu_dictionary_update_40047()
 */
#[Group('mukurtu_dictionary')]
class GlossaryOrderManagerPermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'mukurtu_dictionary'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::moduleHandler()->loadInclude('mukurtu_dictionary', 'install');
  }

  /**
   * The hook grants the permission to an existing manager role.
   */
  public function testGrantsPermissionToManager(): void {
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();
    $this->assertFalse(Role::load('mukurtu_manager')->hasPermission('administer dictionary glossary order'));

    mukurtu_dictionary_update_40047();
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer dictionary glossary order'));

    // Running it again leaves the grant in place without error.
    mukurtu_dictionary_update_40047();
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer dictionary glossary order'));
  }

  /**
   * The hook does nothing on a site without the manager role.
   */
  public function testMissingRoleIsNoop(): void {
    mukurtu_dictionary_update_40047();
    $this->assertNull(Role::load('mukurtu_manager'));
  }

}
