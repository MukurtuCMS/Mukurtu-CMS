<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\OptionalModuleMenuLinkHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests dashboard links to optional modules are dropped once uninstalled.
 *
 * Klaro is installed by default but can be uninstalled per site. Its
 * "Cookie & Consent Settings" dashboard link must then disappear rather than
 * point at a route that no longer exists.
 *
 * The hook is called directly against the shipped *.links.menu.yml so the
 * test does not need mukurtu_core's full dependency tree.
 */
#[CoversClass(OptionalModuleMenuLinkHooks::class)]
#[Group('mukurtu_core')]
class OptionalModuleMenuLinkHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Returns mukurtu_core's shipped menu links after the alter hook runs.
   */
  protected function alteredLinks(): array {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $links = Yaml::parseFile(\Drupal::root() . "/$path/mukurtu_core.links.menu.yml");
    (new OptionalModuleMenuLinkHooks())->menuLinksDiscoveredAlter($links);
    return $links;
  }

  /**
   * Every optional link is shipped, so the map cannot silently go stale.
   */
  public function testOptionalLinksAreShipped(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $links = Yaml::parseFile(\Drupal::root() . "/$path/mukurtu_core.links.menu.yml");
    foreach (array_keys(OptionalModuleMenuLinkHooks::OPTIONAL_LINKS) as $link_id) {
      $this->assertArrayHasKey($link_id, $links);
    }
    $this->assertSame('klaro.admin', $links['mukurtu_core.klaro_settings']['route_name']);
  }

  /**
   * The Klaro settings link is removed when Klaro is not installed.
   */
  public function testKlaroLinkRemovedWithoutKlaro(): void {
    $links = $this->alteredLinks();
    $this->assertArrayNotHasKey('mukurtu_core.klaro_settings', $links);
    // Links to required modules are untouched.
    $this->assertArrayHasKey('mukurtu_core.visitors_analytics', $links);
  }

  /**
   * The Klaro settings link is kept when Klaro is installed.
   */
  public function testKlaroLinkKeptWithKlaro(): void {
    $this->enableModules(['klaro']);
    $this->assertArrayHasKey('mukurtu_core.klaro_settings', $this->alteredLinks());
  }

}
