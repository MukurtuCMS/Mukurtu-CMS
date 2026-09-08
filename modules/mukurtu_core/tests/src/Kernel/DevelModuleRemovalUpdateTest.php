<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40123(), which uninstalls the development modules
 * that earlier releases enabled on every site via the profile's dependencies.
 *
 * Coverage note: KernelTestBase runs with no active install profile, so
 * profile-nested modules such as mukurtu_dev are absent from
 * ExtensionList::getList() and getAllAvailableInfo() here, and
 * ModuleInstaller::uninstall() refuses a list containing an extension it cannot
 * find. mukurtu_dev's removal therefore cannot be asserted in a kernel test and
 * is verified against a real site instead (see the pull request). devel and
 * devel_generate live under web/modules/contrib, so they are fully covered.
 *
 * @see mukurtu_core_update_40123()
 */
#[Group('mukurtu_core')]
class DevelModuleRemovalUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';

    // Uninstalling a module makes core delete that module's rows from
    // users_data (see user_module_preuninstall()), so the table has to exist.
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Returns the list of currently installed modules.
   */
  protected function installedModules(): array {
    return array_keys(\Drupal::config('core.extension')->get('module') ?? []);
  }

  /**
   * Skips the test when devel is not present on disk.
   *
   * devel reaches CI through the mukurtu-template's require-dev rather than
   * this profile's own composer.json, so it is not guaranteed to be there.
   */
  protected function requireDevelOnDisk(): void {
    if (!\Drupal::service('extension.list.module')->exists('devel')) {
      $this->markTestSkipped('The devel module is not present on disk.');
    }
  }

  /**
   * The update hook uninstalls both devel modules when they are installed.
   */
  public function testUpdateUninstallsDevelModules(): void {
    $this->requireDevelOnDisk();

    $this->enableModules(['devel', 'devel_generate']);
    $installed = $this->installedModules();
    $this->assertContains('devel', $installed);
    $this->assertContains('devel_generate', $installed);

    mukurtu_core_update_40123();

    $installed = $this->installedModules();
    $this->assertNotContains('devel', $installed);
    $this->assertNotContains('devel_generate', $installed);
  }

  /**
   * Modules unrelated to the devel stack are left installed.
   */
  public function testUpdateLeavesOtherModulesInstalled(): void {
    $this->requireDevelOnDisk();

    $this->enableModules(['devel']);
    $this->assertContains('user', $this->installedModules());

    mukurtu_core_update_40123();

    $installed = $this->installedModules();
    $this->assertNotContains('devel', $installed);
    $this->assertContains('user', $installed);
    $this->assertContains('system', $installed);
  }

  /**
   * The update hook uninstalls devel even when the wrapper module is absent.
   */
  public function testUpdateUninstallsDevelWithoutWrapper(): void {
    $this->requireDevelOnDisk();

    $this->enableModules(['devel']);
    $this->assertContains('devel', $this->installedModules());

    mukurtu_core_update_40123();

    $this->assertNotContains('devel', $this->installedModules());
  }

  /**
   * Uninstalling devel strips its permission from the administrator role.
   *
   * This is why the update hook does no permission cleanup of its own: Drupal
   * calls Role::onDependencyRemoval() during the uninstall.
   */
  public function testUpdateStripsDevelPermissionFromRoles(): void {
    $this->requireDevelOnDisk();

    $this->enableModules(['devel']);

    $role = Role::create(['id' => 'administrator', 'label' => 'Administrator']);
    $role->grantPermission('access devel information');
    $role->grantPermission('access administration pages');
    $role->save();
    $this->assertTrue(Role::load('administrator')->hasPermission('access devel information'));

    mukurtu_core_update_40123();

    $role = Role::load('administrator');
    $this->assertNotNull($role);
    $this->assertFalse($role->hasPermission('access devel information'));
    // Unrelated permissions survive.
    $this->assertTrue($role->hasPermission('access administration pages'));
  }

  /**
   * The update hook is a no-op when none of the modules are installed.
   *
   * This is the path every site that installed fresh will take.
   */
  public function testUpdateIsNoOpWhenNothingInstalled(): void {
    $before = $this->installedModules();
    $this->assertNotContains('devel', $before);
    $this->assertNotContains('devel_generate', $before);
    $this->assertNotContains('mukurtu_dev', $before);

    mukurtu_core_update_40123();

    $this->assertSame($before, $this->installedModules());
  }

  /**
   * The update hook is idempotent.
   */
  public function testUpdateIsIdempotent(): void {
    $this->requireDevelOnDisk();

    $this->enableModules(['devel']);

    mukurtu_core_update_40123();
    $after_first = $this->installedModules();

    mukurtu_core_update_40123();

    $this->assertSame($after_first, $this->installedModules());
    $this->assertNotContains('devel', $this->installedModules());
  }

}
