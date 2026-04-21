<?php

namespace Drupal\Tests\rebuild_cache_access\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests rebuild_cache_access toolbar functionality.
 *
 * @group rebuild_cache_access
 */
class ToolbarTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toolbar', 'rebuild_cache_access'];

  /**
   * Tests that the toolbar button works.
   */
  public function testButton() {

    // First, a user without rebuild cache permission should not see the button.
    $editor = $this->drupalCreateUser([
      'access toolbar',
    ]);
    $this->drupalLogin($editor);

    $this->drupalGet('');
    $this->assertSession()->pageTextNotContains('Rebuild Cache');

    // Second, a user with rebuild cache permission should see the button.
    $developer = $this->drupalCreateUser([
      'access toolbar',
      'rebuild cache access',
    ]);
    $this->drupalLogin($developer);

    $this->drupalGet('');
    $this->assertSession()->pageTextContains('Rebuild Cache');

    // Click the button.
    $this->clickLink('Rebuild Cache');
    $this->assertSession()->pageTextContains('All caches cleared.');
  }

}
