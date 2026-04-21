<?php

namespace Drupal\Tests\search_api_db\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that using the DB backend via the UI works as expected.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class IntegrationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * The theme to install as the default for testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that adding a server works.
   */
  public function testAddingServer() {
    $admin_user = $this->drupalCreateUser(['administer search_api', 'access content']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/search/search-api/add-server');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Add search server');

    $page->fillField('name', ' ~`Test Server');
    $machine_name = $assert_session->waitForElementVisible('css', '[name="name"] + * .machine-name-value');
    $this->assertNotEmpty($machine_name);
    $page->findButton('Edit')->press();
    $page->fillField('id', '_test');
    $page->pressButton('Save');

    $assert_session->addressEquals('admin/config/search/search-api/server/_test');
  }

}
