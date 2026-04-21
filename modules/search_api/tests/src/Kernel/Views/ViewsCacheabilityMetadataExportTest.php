<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Core\Config\Config;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that cacheability metadata is included when Views config is exported.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsCacheabilityMetadataExportTest extends KernelTestBase {

  /**
   * The ID of the view used in the test.
   */
  protected const TEST_VIEW_ID = 'search_api_test_node_view';

  /**
   * The display IDs used in the test.
   */
  protected const TEST_VIEW_DISPLAY_IDS = [
    'default',
    'page_1',
    'page_2',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The service that is responsible for creating Views executable objects.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewExecutableFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'search_api',
    'search_api_db',
    'search_api_test_node_indexing',
    'search_api_test_views',
    'system',
    'text',
    'user',
    'views',
    'views_test_data',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');

    $this->installConfig([
      'search_api',
      'search_api_test_node_indexing',
    ]);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->viewExecutableFactory = $this->container->get('views.executable');
    $this->state = $this->container->get('state');
  }

  /**
   * Tests that an exported view contains the right cacheability metadata.
   */
  public function testViewExport() {
    $expected_cacheability_metadata = [
      'contexts' => [
        // Search API uses the core EntityFieldRenderer for rendering content.
        // This has support for translatable content, so the result varies by
        // content language.
        // @see \Drupal\views\Entity\Render\EntityFieldRenderer::getCacheContexts()
        'languages:language_content',
        // By default, Views always adds the interface language cache context
        // since it is very likely that there will be translatable strings in
        // the result.
        // @see \Drupal\views\Entity\View::addCacheMetadata()
        'languages:language_interface',
        // Our test view has a pager so we expect it to vary by query arguments.
        // @see \Drupal\views\Plugin\views\pager\SqlBase::getCacheContexts()
        'url.query_args',
        // The test view is a listing of nodes returned as a search result. It
        // is expected to have the list cache contexts of the node entity type.
        // This is defined in the "list_cache_contexts" key of the node entity
        // annotation.
        'user.node_grants:view',
      ],
      'tags' => [
        // Our test view depends on the search index, so whenever the index
        // configuration changes the cached results should be invalidated.
        // @see \Drupal\search_api\Query\Query::getCacheTags()
        'config:search_api.index.test_node_index',
      ],
      // By default the result is permanently cached.
      'max-age' => -1,
    ];
    $expected_view_metadata = [];
    foreach (self::TEST_VIEW_DISPLAY_IDS as $display_id) {
      $expected_view_metadata[$display_id] = $expected_cacheability_metadata;
    }

    // Check that our test view has the expected cacheability metadata.
    $view = $this->getView();
    $this->assertViewCacheabilityMetadata($expected_view_metadata, $view);

    // For efficiency Views calculates the cacheability metadata whenever a view
    // is saved, and includes it in the exported configuration.
    // @see \Drupal\views\Entity\View::addCacheMetadata()
    // Check that the exported configuration contains the expected metadata.
    $view_config = $this->config('views.view.' . self::TEST_VIEW_ID);
    $this->assertViewConfigCacheabilityMetadata($expected_view_metadata, $view_config);

    // Test that modules are able to alter the cacheability metadata. Our test
    // hook implementation will alter all 3 types of metadata.
    // @see search_api_test_views_search_api_query_alter()
    foreach (self::TEST_VIEW_DISPLAY_IDS as $display_id) {
      $expected_view_metadata[$display_id]['contexts'][] = 'views_test_cache_context';
      $expected_view_metadata[$display_id]['tags'][] = 'search_api:test_tag';
      [$plugin_id] = explode('_', $display_id, 2);
      $expected_view_metadata[$display_id]['tags'][] = "search_api:test_views_$plugin_id:search_api_test_node_view__$display_id";
      $expected_view_metadata[$display_id]['tags'][] = 'search_api_list:test_node_index';
      $expected_view_metadata[$display_id]['max-age'] = 100;
    }

    // Activate the alter hook and resave the view so it will recalculate the
    // cacheability metadata.
    $this->state->set('search_api_test_views.alter_query_cacheability_metadata', TRUE);
    $view = $this->getView();
    $view->save();

    // Check that the altered metadata is now present in the view and the
    // configuration.
    $view = $this->getView();
    $this->assertViewCacheabilityMetadata($expected_view_metadata, $view);

    $view_config = $this->config('views.view.' . self::TEST_VIEW_ID);
    $this->assertViewConfigCacheabilityMetadata($expected_view_metadata, $view_config);
  }

  /**
   * Checks that the given view has the expected cacheability metadata.
   *
   * @param array[] $expected_cacheability_metadata
   *   Arrays of cacheability metadata that are expected to be present on the
   *   various displays of the view, keyed by display ID.
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  protected function assertViewCacheabilityMetadata(array $expected_cacheability_metadata, ViewExecutable $view) {
    // Cacheability metadata is stored separately for each Views display since
    // depending on how the display is configured it might have different
    // caching needs. Ensure to check all displays.
    foreach (self::TEST_VIEW_DISPLAY_IDS as $display_id) {
      $view->setDisplay($display_id);
      $display = $view->getDisplay();
      $actual_cacheability_metadata = $display->getCacheMetadata();

      $this->assertArrayEquals($expected_cacheability_metadata[$display_id]['contexts'], $actual_cacheability_metadata->getCacheContexts());
      $this->assertArrayEquals($expected_cacheability_metadata[$display_id]['tags'], $actual_cacheability_metadata->getCacheTags());
      $this->assertEquals($expected_cacheability_metadata[$display_id]['max-age'], $actual_cacheability_metadata->getCacheMaxAge());
    }
  }

  /**
   * Checks that the given view config has the expected cacheability metadata.
   *
   * @param array[] $expected_cacheability_metadata
   *   Arrays of cacheability metadata that are expected to be present in the
   *   configuration of the various displays of the view, keyed by display ID.
   * @param \Drupal\Core\Config\Config $config
   *   The configuration to check.
   */
  protected function assertViewConfigCacheabilityMetadata(array $expected_cacheability_metadata, Config $config) {
    // Cacheability metadata is stored separately for each Views display since
    // depending on how the display is configured it might have different
    // caching needs. Ensure to check all displays.
    foreach (self::TEST_VIEW_DISPLAY_IDS as $display_id) {
      $view_config_display = $config->get("display.$display_id");
      foreach ($expected_cacheability_metadata[$display_id] as $cache_key => $value) {
        if (is_array($value)) {
          $this->assertArrayEquals($value, $view_config_display['cache_metadata'][$cache_key]);
        }
        else {
          $this->assertEquals($value, $view_config_display['cache_metadata'][$cache_key]);
        }
      }
    }
  }

  /**
   * Checks that the given arrays have the same values.
   *
   * @param array $array1
   *   One of the arrays to compare.
   * @param array $array2
   *   One of the arrays to compare.
   */
  protected function assertArrayEquals(array $array1, array $array2) {
    sort($array1);
    sort($array2);
    $this->assertEquals($array1, $array2);
  }

  /**
   * Returns the test view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  protected function getView() {
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $this->entityTypeManager->getStorage('view')
      ->load(self::TEST_VIEW_ID);
    $executable = $this->viewExecutableFactory->get($view);

    return $executable;
  }

}
