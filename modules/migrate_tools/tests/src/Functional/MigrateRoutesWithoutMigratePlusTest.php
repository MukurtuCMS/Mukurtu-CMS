<?php

namespace Drupal\Tests\migrate_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that migrate routes do not break when migrate_plus is missing.
 *
 * @group migrate_tools
 */
class MigrateRoutesWithoutMigratePlusTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   *
   * Only enable migrate_tools, not migrate_plus.
   */
  protected static $modules = [
    'migrate_tools',
  ];

  /**
   * Theme used for UI rendering in tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Test that accessing /admin/structure/migrate does not fatal.
   */
  public function testMigrateRoutesWithoutMigratePlus() {
    $account = $this->drupalCreateUser([
      'administer migrations',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/structure/migrate');
    $this->assertSession()->statusCodeNotEquals(500);
    $this->assertTrue(
      in_array($this->getSession()->getStatusCode(), [200, 403, 404]),
      'The /admin/structure/migrate route does not cause a fatal when migrate_plus is missing.'
    );
  }

}
