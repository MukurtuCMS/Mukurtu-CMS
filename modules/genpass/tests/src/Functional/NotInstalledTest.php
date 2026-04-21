<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests if Drupal core is working without and with Generate Password installed.
 *
 * @group genpass
 */
class NotInstalledTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with "administer account settings".
   *
   * And "administer users" permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer account settings',
      'administer users',
    ]);
  }

  /**
   * Check to see that Drupal core is working, then install genpass.
   */
  public function testDrupalWorkingWithoutAndWith(): void {
    // Ensure Generate Password is not installed.
    $module_handler = \Drupal::service('module_handler');
    $this->assertFalse(
      $module_handler->moduleExists('genpass'),
      'Generate Password module is unexpectedly installed.'
    );

    // Check front page is working.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Currently anonymous - try accessing admin pages.
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(403);

    // Check that Drupal is working before installing Generate Password.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);

    // Install Generate Password.
    $this->assertTrue(
      \Drupal::service('module_installer')
        ->install(['genpass']),
      'Failed to install Generate Password module.'
    );

    // Is installed now.
    $this->assertTrue(
      $module_handler->moduleExists('genpass'),
      'Generate Password module is unexpectedly not installed.'
    );

    // Check that the same page is still accessible.
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);

    // Check all of the form changes.
    $this->assertSession()->pageTextContains('Account settings');
    $this->assertSession()->pageTextContains('Generate Password - User Account Registration');
    $this->assertSession()->pageTextContains('User password entry');
    $this->assertSession()->pageTextContains('Admin password entry');
    $this->assertSession()->pageTextContains('Generated password length');
    $this->assertSession()->pageTextContains('Generated password display');
  }

}
