<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_gin_custom does not require Klaro.
 *
 * The mukurtu_gin_custom module is a hard profile dependency. It used to depend
 * on Klaro only to ship the Toastify klaro_app, which made Klaro impossible to
 * uninstall. The klaro_app now ships as optional config instead.
 */
#[Group('mukurtu_gin_custom')]
class KlaroOptionalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Uninstalling a module clears its rows from users_data.
    $this->installSchema('user', ['users_data']);
  }

  /**
   * The module declares no dependency on Klaro.
   */
  public function testNoKlaroDependency(): void {
    $path = $this->container->get('extension.list.module')->getPathname('mukurtu_gin_custom');
    $info = $this->container->get('info_parser')->parse($path);
    $this->assertNotContains('klaro:klaro', $info['dependencies'] ?? []);
    $this->assertNotContains('klaro', $info['dependencies'] ?? []);
  }

  /**
   * The module installs and stays installed without Klaro.
   */
  public function testInstallsWithoutKlaro(): void {
    $installer = $this->container->get('module_installer');

    $installer->install(['mukurtu_gin_custom']);
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('mukurtu_gin_custom'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('klaro'));
    $this->assertTrue(\Drupal::config('klaro.klaro_app.toastify')->isNew());

    $installer->install(['klaro']);
    $installer->uninstall(['klaro']);
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('mukurtu_gin_custom'));
    $this->assertTrue(\Drupal::config('klaro.klaro_app.toastify')->isNew());
  }

  /**
   * The Toastify app is created when Klaro is installed after this module.
   *
   * On module install, core creates other modules' optional config that
   * depends on the new module, and a config entity implicitly depends on the
   * module that provides its type. Core finds that config through
   * ExtensionInstallStorage, which cannot see profile-nested modules when no
   * install profile is active, so this hands the installer the module's
   * optional storage directly with the same dependency filter core uses.
   *
   * @see \Drupal\Core\Config\ConfigInstaller::installDefaultConfig()
   */
  public function testToastifyAppFollowsKlaro(): void {
    $installer = $this->container->get('module_installer');
    $installer->install(['mukurtu_gin_custom']);
    $installer->install(['klaro']);

    $path = \Drupal::root() . '/' . $this->container->get('extension.list.module')->getPath('mukurtu_gin_custom') . '/' . InstallStorage::CONFIG_OPTIONAL_DIRECTORY;
    \Drupal::service('config.installer')->installOptionalConfig(new FileStorage($path), ['module' => 'klaro']);

    $app = \Drupal::config('klaro.klaro_app.toastify');
    $this->assertFalse($app->isNew());
    $this->assertTrue($app->get('required'));
  }

}
