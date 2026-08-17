<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures mukurtu_multilingual never re-ships config that collides by name.
 *
 * Drupal's ConfigInstaller throws a fatal "already exist in active
 * configuration" error for any config object name collision at install
 * time, regardless of which module owns it or whether the content differs
 * (see #1991). This is a structural regression guard for that class of bug.
 */
#[Group('mukurtu_multilingual')]
class ConfigInstallCollisionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * No mukurtu_multilingual config/install filename collides with another.
   *
   * Checks every other module's config/install or config/optional
   * directory for a matching filename.
   */
  public function testNoConfigInstallCollisions(): void {
    $module_list = \Drupal::service('extension.list.module');
    $all_modules = $module_list->getList();
    $this->assertArrayHasKey('mukurtu_multilingual', $all_modules);

    $multilingual_path = \Drupal::root() . '/' . $all_modules['mukurtu_multilingual']->getPath() . '/config/install';
    $multilingual_names = $this->listConfigNames($multilingual_path);
    $this->assertNotEmpty($multilingual_names, 'mukurtu_multilingual should ship default config.');

    $collisions = [];
    foreach ($all_modules as $name => $extension) {
      if ($name === 'mukurtu_multilingual') {
        continue;
      }
      foreach (['config/install', 'config/optional'] as $config_dir) {
        $path = \Drupal::root() . '/' . $extension->getPath() . '/' . $config_dir;
        foreach ($this->listConfigNames($path) as $config_name) {
          if (isset($multilingual_names[$config_name])) {
            $collisions[] = "$config_name (also shipped by $name/$config_dir)";
          }
        }
      }
    }

    $this->assertSame([], $collisions, "mukurtu_multilingual/config/install must not collide with other modules' shipped config:\n" . implode("\n", $collisions));
  }

  /**
   * Returns config object names (without the .yml suffix) for a directory.
   *
   * @return array<string, true>
   *   Config names as keys for fast lookup.
   */
  private function listConfigNames(string $path): array {
    $names = [];
    if (!is_dir($path)) {
      return $names;
    }
    foreach (glob($path . '/*.yml') as $file) {
      $names[basename($file, '.yml')] = TRUE;
    }
    return $names;
  }

}
