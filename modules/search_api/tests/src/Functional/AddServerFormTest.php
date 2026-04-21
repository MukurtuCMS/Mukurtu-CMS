<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Add server" form.
 *
 * @see \Drupal\search_api\Form\ServerForm
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class AddServerFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
  ];

  /**
   * The theme to install as the default for testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create the users used for the tests.
    $adminUser = $this->drupalCreateUser([
      'administer search_api',
      'access administration pages',
    ]);

    $this->drupalLogin($adminUser);
  }

  /**
   * Tests the behavior when no backend plugins are available.
   */
  public function testNoBackendPluginsArePresent() {
    $this->drupalGet('/admin/config/search/search-api/add-server');
    $this->assertSession()->buttonNotExists('Save');
    $this->assertSession()->pageTextContainsOnce('There are no backend plugins available for the Search API.');

    \Drupal::getContainer()->get('module_installer')
      ->install(['search_api_test']);

    $this->drupalGet('/admin/config/search/search-api/add-server');
    $this->assertSession()->buttonExists('Save');
    $this->assertSession()->pageTextNotContains('There are no backend plugins available for the Search API.');
  }

}
