<?php

declare(strict_types=1);

namespace Drupal\Tests\rebuild_cache_access\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests rebuild_cache_access navigation block functionality.
 *
 * @group rebuild_cache_access
 */
#[Group('rebuild_cache_access')]
class NavigationBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation', 'rebuild_cache_access'];

  /**
   * Tests the navigation block is visible only to users with permission.
   */
  public function testNavigationBlockPermissions(): void {
    // A user with only navigation access should not see the rebuild cache link.
    $user_without_permission = $this->drupalCreateUser([
      'access navigation',
    ]);
    $this->drupalLogin($user_without_permission);
    $this->drupalGet('');
    $this->assertSession()->pageTextNotContains('Rebuild cache');

    // A user with both permissions should see the rebuild cache link.
    $user_with_permission = $this->drupalCreateUser([
      'access navigation',
      'rebuild cache access',
    ]);
    $this->drupalLogin($user_with_permission);
    $this->drupalGet('');
    $this->assertSession()->pageTextContains('Rebuild cache');
  }

  /**
   * Tests clicking the navigation block link rebuilds the cache.
   */
  public function testNavigationBlockRebuildsCache(): void {
    $user = $this->drupalCreateUser([
      'access navigation',
      'rebuild cache access',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('');
    $this->clickLink('Rebuild cache');
    $this->assertSession()->pageTextContains('All caches cleared.');
  }

  /**
   * Tests the block definition has the correct navigation-related flags.
   */
  public function testNavigationBlockDefinitionFlags(): void {
    $block_manager = $this->container->get('plugin.manager.block');
    $definitions = $block_manager->getDefinitions();
    $this->assertArrayHasKey('rebuild_cache_access_navigation', $definitions);
    $definition = $definitions['rebuild_cache_access_navigation'];
    $this->assertTrue($definition['allow_in_navigation'], 'Block is allowed in navigation layout.');
    $this->assertTrue($definition['_block_ui_hidden'], 'Block is hidden from the block UI.');
  }

  /**
   * Tests the navigation block is hidden from the standard block placement UI.
   */
  public function testNavigationBlockHiddenFromBlockUi(): void {
    $admin = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/block');
    $this->clickLink('Place block');
    $this->assertSession()->linkByHrefNotExists('/admin/structure/block/add/rebuild_cache_access_navigation/stark');
  }

}
