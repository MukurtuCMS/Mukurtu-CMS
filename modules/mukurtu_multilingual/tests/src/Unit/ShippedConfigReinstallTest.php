<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Keeps mukurtu_multilingual installable after an uninstall (#2374).
 *
 * Its base field overrides and content language settings belong to other
 * modules' entity types, so uninstalling mukurtu_multilingual leaves them in
 * active config. Drupal's ConfigInstaller refuses to install a module whose
 * config/install ships a name that already exists, so turning multilingual
 * back on failed, from Extend and from the dashboard's "Enable multilingual"
 * link alike. Optional config that already exists is skipped instead, so
 * that config ships from config/optional.
 *
 * Config that depends on mukurtu_multilingual itself, such as a view it
 * provides, is deleted on uninstall, so it can still ship in config/install.
 *
 * A pure filesystem check - no Drupal bootstrap needed.
 */
#[Group('mukurtu_multilingual')]
class ShippedConfigReinstallTest extends UnitTestCase {

  /**
   * Every config/install file is removed when the module is uninstalled.
   */
  public function testInstallConfigIsRemovedOnUninstall(): void {
    $module_root = dirname(__DIR__, 3);
    $survivors = [];
    foreach (glob($module_root . '/config/install/*.yml') ?: [] as $file) {
      $dependencies = Yaml::decode(file_get_contents($file))['dependencies'] ?? [];
      $modules = array_merge($dependencies['module'] ?? [], $dependencies['enforced']['module'] ?? []);
      if (!in_array('mukurtu_multilingual', $modules, TRUE)) {
        $survivors[] = basename($file);
      }
    }
    $this->assertSame([], $survivors, 'These would survive an uninstall and block reinstalling mukurtu_multilingual. Move them to config/optional.');
  }

  /**
   * The shipped config is still there, in config/optional.
   */
  public function testConfigShipsAsOptional(): void {
    $module_root = dirname(__DIR__, 3);
    $this->assertFileExists($module_root . '/mukurtu_multilingual.info.yml', 'Sanity check: resolved module root is wrong.');
    $this->assertGreaterThan(200, count(glob($module_root . '/config/optional/*.yml') ?: []));
  }

}
