<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts the admin dashboard's menu blocks all point at menus that exist.
 *
 * The dashboard is assembled from system_menu_block components, each naming a
 * menu by id. Nothing validates that the menu is actually installed, so a
 * component referencing a missing menu renders as an empty region: the
 * dashboard still loads, the section is simply not there. That is easy to ship
 * and hard to notice.
 *
 * The pairing is also split across owners. Sixteen of the menus are shipped by
 * the profile, but dashboard-security comes from mukurtu_bot_protection while
 * the component referencing it lives in the profile's dashboard config, so the
 * two can drift independently. An update hook used to add that component, and
 * its test checked only that one pairing. This checks all of them.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
class ShippedDashboardConfigTest extends UnitTestCase {

  /**
   * Resolves the profile root from this file's location.
   */
  private function profileRoot(): string {
    $root = dirname(__DIR__, 3);
    $this->assertFileExists("$root/mukurtu.info.yml", 'Sanity check: resolved profile root is wrong.');
    return $root;
  }

  /**
   * Every menu id referenced by a system_menu_block component.
   *
   * Components are nested inside the dashboard's sections, so the structure is
   * walked rather than assuming a fixed depth.
   */
  private function referencedMenus(array $config): array {
    $menus = [];
    $walk = static function ($node) use (&$walk, &$menus): void {
      if (!is_array($node)) {
        return;
      }
      $id = $node['id'] ?? NULL;
      if (is_string($id) && str_starts_with($id, 'system_menu_block:')) {
        $menus[] = substr($id, strlen('system_menu_block:'));
      }
      foreach ($node as $child) {
        $walk($child);
      }
    };
    $walk($config);

    return $menus;
  }

  /**
   * Loads the shipped dashboard config.
   */
  private function dashboard(): array {
    $path = $this->profileRoot() . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml';
    $this->assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Every referenced menu is shipped somewhere in the profile.
   */
  public function testEveryDashboardMenuBlockHasItsMenu(): void {
    $root = $this->profileRoot();
    $menus = $this->referencedMenus($this->dashboard());

    $this->assertGreaterThanOrEqual(17, count($menus), 'Found almost no menu blocks; the dashboard structure has changed shape.');

    foreach (array_unique($menus) as $menu) {
      $candidates = array_merge(
        glob("$root/config/install/system.menu.$menu.yml") ?: [],
        glob("$root/modules/*/config/install/system.menu.$menu.yml") ?: []
      );

      $this->assertNotEmpty(
        $candidates,
        "The dashboard renders a block for the '$menu' menu, but no module ships that menu, so the section will be empty."
      );
    }
  }

  /**
   * No menu is rendered twice.
   *
   * A duplicated component shows the operator the same list of links in two
   * places, which is what one of the replaced update hooks existed to fix.
   */
  public function testNoMenuIsRenderedTwice(): void {
    $menus = $this->referencedMenus($this->dashboard());
    $counts = array_count_values($menus);

    foreach ($counts as $menu => $count) {
      $this->assertSame(1, $count, "The '$menu' menu is rendered $count times on the dashboard.");
    }
  }

  /**
   * The security section is present.
   *
   * Called out separately because it is the cross-module pairing: the component
   * is in the profile's config and the menu is in mukurtu_bot_protection.
   */
  public function testSecuritySectionIsPresent(): void {
    $this->assertContains(
      'dashboard-security',
      $this->referencedMenus($this->dashboard()),
      'The dashboard no longer renders the security section.'
    );

    $this->assertFileExists(
      $this->profileRoot() . '/modules/mukurtu_bot_protection/config/install/system.menu.dashboard-security.yml',
      'mukurtu_bot_protection no longer ships the dashboard-security menu the dashboard references.'
    );
  }

}
