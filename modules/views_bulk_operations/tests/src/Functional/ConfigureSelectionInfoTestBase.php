<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Functional;

use Drupal\Core\Config\Config;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for tests for the Selection Info controls.
 */
abstract class ConfigureSelectionInfoTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The number of results per page that the test view is configured to show.
   *
   * @var int
   */
  protected int $numberOfResultsPerPage;

  /**
   * The configuration of the view we are testing with.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $testViewConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Log in as a user that can access the views_bulk_operations_test
    // view's page_1 display.
    $this->drupalLogin($this->createUser([
      'access content',
    ]));

    $this->testViewConfiguration = $this->config('views.view.views_bulk_operations_test');
    $this->numberOfResultsPerPage = $this->testViewConfiguration->get('display.default.display_options.pager.options.items_per_page');
  }

  /**
   * Count the number of nodes in content storage.
   *
   * @return int
   *   The number of nodes in content storage.
   */
  protected function countNumberOfNodesInSystem(): int {
    $query = $this->container->get('entity_type.manager')->getStorage('node')->getQuery();
    $query->count();
    $query->accessCheck();
    $result = $query->execute();
    self::assertIsInt($result, 'Node count query sanity check');
    return $result;
  }

  /**
   * Create an arbitrary number of random 'page' nodes.
   *
   * @param int $numToCreate
   *   The number of pages to create.
   */
  protected function createPageNodes(int $numToCreate): void {
    for ($i = 0; $i < $numToCreate; $i++) {
      $this->drupalCreateNode(['type' => 'page']);
    }
  }

  /**
   * Find all 'show_multipage_selection_box' boxes on the page.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   All 'show_multipage_selection_box' boxes currently on the page.
   */
  protected function findMultipageSelectionBoxOnPage(): array {
    return $this->getSession()->getPage()
      ->findAll('css', 'details.vbo-multipage-selector');
  }

  /**
   * Find all 'select_all' checkboxes on the page.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   All 'select_all' checkboxes currently on the page.
   */
  protected function findSelectAllCheckboxesOnPage(): array {
    return $this->xpath('//input[@type="checkbox"][@name="select_all"]');
  }

  /**
   * Set a given view's default display's 'show_multipage_selection_box' config.
   *
   * @param \Drupal\Core\Config\Config $viewConfig
   *   The configuration of the view we are testing with.
   * @param string $newValue
   *   The new value for the 'show_multipage_selection_box' configuration
   *   option.
   */
  protected function setMultipageSelectionBoxVisibility(Config $viewConfig, string $newValue): void {
    $viewConfig->set('display.default.display_options.fields.views_bulk_operations_bulk_form.show_multipage_selection_box', $newValue);
    $viewConfig->set('display.default.display_options.title', \sprintf('show_multipage_selection_box: "%s"', $newValue));
    $viewConfig->save();
  }

  /**
   * Set a given view's default display's 'show_select_all' config option.
   *
   * @param \Drupal\Core\Config\Config $viewConfig
   *   The configuration of the view we are testing with.
   * @param string $newValue
   *   The new value for the 'show_select_all' configuration option.
   */
  protected function setSelectAllVisibilityConfiguration(Config $viewConfig, string $newValue): void {
    $viewConfig->set('display.default.display_options.fields.views_bulk_operations_bulk_form.show_select_all', $newValue);
    $viewConfig->set('display.default.display_options.title', \sprintf('show_select_all: "%s"', $newValue));
    $viewConfig->save();
  }

}
