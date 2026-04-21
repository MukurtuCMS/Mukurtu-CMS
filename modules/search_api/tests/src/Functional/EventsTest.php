<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\search_api\Entity\Index;
use Drupal\search_api_test\PluginTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests integration of events.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class EventsTest extends SearchApiBrowserTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'rest',
    'search_api',
    'search_api_test',
    'search_api_test_views',
    'search_api_test_events',
  ];

  /**
   * The test server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create some nodes.
    $this->drupalCreateNode(['type' => 'page', 'title' => 'node - 1']);
    $this->drupalCreateNode(['type' => 'page', 'title' => 'node - 2']);
    $this->drupalCreateNode(['type' => 'page', 'title' => 'node - 3']);
    $this->drupalCreateNode(['type' => 'page', 'title' => 'node - 4']);

    // Create an index and server to work with.
    $this->server = $this->getTestServer();
    $index = $this->getTestIndex();

    // Add the test processor to the index so we can make sure that all expected
    // processor methods are called, too.
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'search_api_test');
    $index->addProcessor($processor)->save();

    // Parts of this test actually use the "database_search_index" from the
    // search_api_test_db module (via the test view). Set the processor there,
    // too.
    $index = Index::load('database_search_index');
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'search_api_test');
    $index->addProcessor($processor)->save();

    // Reset the called methods on the processor.
    $this->getCalledMethods('processor');

    // Log in, so we can test all the things.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests whether events are correctly dispatched when using the admin UI.
   */
  public function testEvents() {
    // The BackendInfo event was invoked.
    $this->drupalGet('admin/config/search/search-api/add-server');
    $this->assertSession()->pageTextContains('Slims return');

    // The DatasourceInfo event was invoked.
    $this->drupalGet('admin/config/search/search-api/add-index');
    $this->assertSession()->pageTextContains('Distant land');
    // The TrackerInfo event was invoked.
    $this->assertSession()->pageTextContains('Good luck');

    // The ProcessorInfo event was invoked.
    $this->drupalGet($this->getIndexPath('processors'));
    $this->assertSession()->pageTextContains('Mystic bounce');

    // The ParseModeInfo event was invoked.
    $definition = \Drupal::getContainer()
      ->get('plugin.manager.search_api.parse_mode')
      ->getDefinition('direct');
    $this->assertEquals('Song for My Father', $definition['label']);

    // Saving the index should trigger the processor's preIndexSave() method.
    $this->submitForm([], 'Save');
    $processor_methods = $this->getCalledMethods('processor');
    $this->assertEquals(['preIndexSave'], $processor_methods);

    $this->drupalGet($this->getIndexPath());
    // Duplication on value 'Index now' with summary.
    $this->submitForm([], 'Index now');
    $this->checkForMetaRefresh();
    $this->assertSession()->pageTextContains('Successfully indexed 4 items.');

    // During indexing, alterIndexedItems() and preprocessIndexItems() should be
    // called on the processor.
    $processor_methods = $this->getCalledMethods('processor');
    $expected = ['alterIndexedItems', 'preprocessIndexItems'];
    $this->assertEquals($expected, $processor_methods);

    // The indexing items event was invoked, this removed node:1.
    $this->assertSession()->pageTextContains('There are 2 items indexed on the server for this index.');
    $this->assertSession()->pageTextContains('Stormy');

    // The ItemsIndexed event was invoked.
    // cspell:disable-next-line
    $this->assertSession()->pageTextContains('Please set me at ease');

    // The Reindex event was invoked.
    $this->drupalGet($this->getIndexPath('reindex'));
    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains('Montara');

    // The DataTypePluginInfo event was invoked.
    $this->drupalGet($this->getIndexPath('fields'));
    $this->assertSession()->pageTextContains('Peace/Dolphin dance');
    // The implementation of hook_search_api_field_type_mapping_alter() has
    // removed all dates, so we can't see any timestamp anymore in the page.
    $url_options['query']['datasource'] = 'entity:node';
    $this->drupalGet($this->getIndexPath('fields/add/nojs'), $url_options);
    $this->assertSession()->pageTextContains('Add fields to index');
    $this->assertSession()->pageTextNotContains('timestamp');

    // The QueryAlter event was invoked.
    $this->drupalGet('search-api-test');
    $this->assertSession()->pageTextContains('Search id: views_page:search_api_test_view__page_1');
    $this->assertSession()->pageTextContains('Funky blue note');
    // The Query(TAG)Alter event was invoked, this removed node:2.
    $this->assertSession()->pageTextContains('Freeland');
    // The ResultsAlter event was invoked.
    $this->assertSession()->pageTextContains('Stepping into tomorrow');
    // THe Results(TAG)Alter event was invoked.
    $this->assertSession()->pageTextContains('Llama');

    // The query alter methods of the processor were called.
    $processor_methods = $this->getCalledMethods('processor');
    $expected = ['preprocessSearchQuery', 'postprocessSearchResults'];
    $this->assertEquals($expected, $processor_methods);

    // The ServerFeaturesAlter hook was invoked.
    $this->assertTrue($this->server->supportsFeature('welcome_to_the_jungle'));

    $displays = \Drupal::getContainer()->get('plugin.manager.search_api.display')
      ->getInstances();
    // The DisplaysAlter event was invoked.
    $display_label = $displays['views_page:search_api_test_view__page_1']->label();
    $this->assertEquals('Some funny label for testing', $display_label);
  }

}
