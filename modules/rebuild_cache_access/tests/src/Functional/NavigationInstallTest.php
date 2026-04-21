<?php

declare(strict_types=1);

namespace Drupal\Tests\rebuild_cache_access\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests navigation block auto-installation order for rebuild_cache_access.
 *
 * @group rebuild_cache_access
 */
#[Group('rebuild_cache_access')]
#[RunTestsInSeparateProcesses]
class NavigationInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['test_page_test'];

  /**
   * Tests block auto-installs when navigation is enabled after this module.
   *
   * Simulates: rebuild_cache_access first, navigation installed later.
   */
  public function testNavigationInstalledAfterModule(): void {
    $test_page_url = Url::fromRoute('test_page_test.test_page');
    $module_installer = $this->container->get('module_installer');

    $module_installer->install(['rebuild_cache_access']);
    $module_installer->install(['navigation']);

    $user = $this->drupalCreateUser([
      'access navigation',
      'rebuild cache access',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet($test_page_url);
    $this->assertSession()->pageTextContains('Rebuild cache');
  }

  /**
   * Tests block auto-installs when this module is enabled after navigation.
   *
   * Simulates: navigation first, rebuild_cache_access installed later.
   */
  public function testModuleInstalledAfterNavigation(): void {
    $test_page_url = Url::fromRoute('test_page_test.test_page');
    $module_installer = $this->container->get('module_installer');

    $module_installer->install(['navigation']);
    $module_installer->install(['rebuild_cache_access']);

    $user = $this->drupalCreateUser([
      'access navigation',
      'rebuild cache access',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet($test_page_url);
    $this->assertSession()->pageTextContains('Rebuild cache');
  }

}
