<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for config_pages theme suggestions and preprocess.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesThemeSuggestionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'config_pages',
  ];

  /**
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    ConfigPagesType::create([
      'id' => 'test_theme',
      'label' => 'Test Theme Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $this->configPage = ConfigPages::create([
      'type' => 'test_theme',
      'label' => 'Test Theme Page',
      'context' => serialize([]),
    ]);
    $this->configPage->save();

    // Ensure the .module file is loaded.
    \Drupal::moduleHandler()->loadInclude('config_pages', 'module');
  }

  /**
   * Tests config_pages theme suggestions with default view mode.
   */
  public function testThemeSuggestionsDefaultViewMode(): void {
    $variables = [
      'elements' => [
        '#config_pages' => $this->configPage,
        '#view_mode' => 'default',
      ],
    ];

    $suggestions = config_pages_theme_suggestions_config_pages($variables);

    $this->assertContains('config_pages__default', $suggestions);
    $this->assertContains('config_pages__test_theme', $suggestions);
    $this->assertContains('config_pages__test_theme__default', $suggestions);
    $this->assertCount(3, $suggestions);
  }

  /**
   * Tests config_pages theme suggestions with custom view mode.
   */
  public function testThemeSuggestionsCustomViewMode(): void {
    $variables = [
      'elements' => [
        '#config_pages' => $this->configPage,
        '#view_mode' => 'teaser',
      ],
    ];

    $suggestions = config_pages_theme_suggestions_config_pages($variables);

    $this->assertContains('config_pages__teaser', $suggestions);
    $this->assertContains('config_pages__test_theme', $suggestions);
    $this->assertContains('config_pages__test_theme__teaser', $suggestions);
  }

  /**
   * Tests view mode with dots is sanitized to underscores.
   */
  public function testThemeSuggestionsViewModeSanitization(): void {
    $variables = [
      'elements' => [
        '#config_pages' => $this->configPage,
        '#view_mode' => 'custom.view.mode',
      ],
    ];

    $suggestions = config_pages_theme_suggestions_config_pages($variables);

    $this->assertContains('config_pages__custom_view_mode', $suggestions);
    $this->assertContains('config_pages__test_theme__custom_view_mode', $suggestions);
  }

  /**
   * Tests block theme suggestions for config_pages_block plugin.
   */
  public function testBlockThemeSuggestions(): void {
    $variables = [
      'elements' => [
        '#plugin_id' => 'config_pages_block',
        '#configuration' => [
          'config_page_type' => 'test_theme',
          'config_page_view_mode' => 'full',
        ],
      ],
      'theme_hook_original' => 'block',
    ];

    $suggestions = config_pages_theme_suggestions_block($variables);

    $this->assertContains('block__config_pages__test_theme', $suggestions);
    $this->assertContains('block__config_pages__test_theme__full', $suggestions);
    $this->assertCount(2, $suggestions);
  }

  /**
   * Tests block theme suggestions for non-config_pages block plugin.
   */
  public function testBlockThemeSuggestionsNonConfigPagesBlock(): void {
    $variables = [
      'elements' => [
        '#plugin_id' => 'system_branding_block',
        '#configuration' => [],
      ],
      'theme_hook_original' => 'block',
    ];

    $suggestions = config_pages_theme_suggestions_block($variables);

    $this->assertEmpty($suggestions);
  }

  /**
   * Tests preprocess adds expected variables.
   */
  public function testPreprocessConfigPages(): void {
    $variables = [
      'elements' => [
        '#config_pages' => $this->configPage,
        '#view_mode' => 'full',
        'field_example' => ['#markup' => 'test'],
      ],
    ];

    config_pages_preprocess_config_pages($variables);

    $this->assertEquals('full', $variables['view_mode']);
    $this->assertSame($this->configPage, $variables['config_pages']);
    $this->assertArrayHasKey('content', $variables);
    $this->assertArrayHasKey('field_example', $variables['content']);
  }

  /**
   * Tests preprocess does not overwrite existing content.
   */
  public function testPreprocessPreservesExistingContent(): void {
    $variables = [
      'elements' => [
        '#config_pages' => $this->configPage,
        '#view_mode' => 'default',
      ],
      'content' => ['existing_key' => 'existing_value'],
    ];

    config_pages_preprocess_config_pages($variables);

    $this->assertArrayHasKey('existing_key', $variables['content']);
    $this->assertEquals('existing_value', $variables['content']['existing_key']);
  }

}
