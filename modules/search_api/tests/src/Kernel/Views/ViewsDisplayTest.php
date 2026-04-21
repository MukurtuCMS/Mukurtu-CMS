<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests whether search display plugins for Views work correctly.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsDisplayTest extends KernelTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_test',
    'field',
    'rest',
    'search_api',
    'search_api_db',
    'search_api_test',
    'search_api_test_db',
    'search_api_test_example_content',
    'search_api_test_views',
    'serialization',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    $this->installConfig([
      'search_api_test_example_content',
      'search_api_test_db',
    ]);
  }

  /**
   * Tests whether the search display plugin for a new view is available.
   */
  public function testViewsPageDisplayPluginAvailable() {
    // Retrieve the display plugins once first, to fill the cache.
    $displays = $this->container
      ->get('plugin.manager.search_api.display')
      ->getDefinitions();
    $this->assertArrayNotHasKey('views_page:search_api_test_view__page_1', $displays);

    // Then, install our test view and see whether its search display becomes
    // available right away, without manually clearing the cache first.
    $this->installConfig('search_api_test_views');
    $displays = $this->container
      ->get('plugin.manager.search_api.display')
      ->getDefinitions();
    $this->assertArrayHasKey('views_page:search_api_test_view__page_1', $displays);
  }

  /**
   * Tests the dependency information on the display.
   */
  public function testDependencyInfo() {
    $this->installConfig('search_api_test_views');

    /** @var \Drupal\search_api\Display\DisplayInterface $display */
    $display = $this->container
      ->get('plugin.manager.search_api.display')
      ->createInstance('views_page:search_api_test_view__page_1');

    $this->assertEquals('views_page:search_api_test_view__page_1', $display->getPluginId());

    $dependencies = $display->calculateDependencies();
    $this->assertArrayHasKey('module', $dependencies);
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains('search_api', $dependencies['module']);
    $this->assertContains('search_api.index.database_search_index', $dependencies['config']);
    $this->assertContains('views.view.search_api_test_view', $dependencies['config']);
  }

  /**
   * Tests whether block displays' "rendered in current request" work correctly.
   */
  public function testBlockRenderedInCurrentRequest() {
    $this->installConfig('search_api_test_views');
    \Drupal::service('theme_installer')->install(['stable9']);
    \Drupal::service('theme_installer')->install(['claro']);

    $block = $this->placeBlock('views_block:search_api_test_block_view-block_1', [
      'theme' => 'stable9',
    ]);
    $this->assertTrue($block->status());

    /** @var \Drupal\search_api\Plugin\search_api\display\ViewsBlock $display */
    $display = $this->container
      ->get('plugin.manager.search_api.display')
      ->createInstance('views_block:search_api_test_block_view__block_1');
    $this->assertEquals('views_block:search_api_test_block_view__block_1', $display->getPluginId());

    $theme = $this->createMock(ActiveTheme::class);
    $theme->method('getName')->willReturn('claro', 'stable9');
    $theme_manager = $this->createMock(ThemeManagerInterface::class);
    $theme_manager->method('getActiveTheme')->willReturn($theme);
    $display->setThemeManager($theme_manager);

    // In the first call, active theme will be reported as "claro", so block
    // would not be rendered; in the second call, with theme "stable9", it
    // should be.
    $this->assertFalse($display->isRenderedInCurrentRequest());
    $this->assertTrue($display->isRenderedInCurrentRequest());
  }

}
