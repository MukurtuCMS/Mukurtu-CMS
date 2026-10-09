<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

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
 * link alike. Optional config that already exists is skipped instead, so all
 * of it ships from config/optional.
 *
 * A pure filesystem check - no Drupal bootstrap needed.
 */
#[Group('mukurtu_multilingual')]
class ShippedConfigReinstallTest extends UnitTestCase {

  /**
   * The module ships no config/install files.
   */
  public function testNoConfigInstallFiles(): void {
    $module_root = dirname(__DIR__, 3);
    $this->assertSame([], glob($module_root . '/config/install/*.yml') ?: []);
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
