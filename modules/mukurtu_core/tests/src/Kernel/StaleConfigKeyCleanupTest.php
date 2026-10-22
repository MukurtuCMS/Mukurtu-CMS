<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\StaleConfigKeyCleanup;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests StaleConfigKeyCleanup, run by mukurtu_core_update_40222().
 *
 * Each case writes config the way an earlier version stored it, bypassing
 * the schema checker as a real site's stored config would, then checks the
 * cleanup removes only the stale keys. The cleanup saves through the config
 * system, so strict schema checking also proves the result is valid.
 *
 * @see mukurtu_core_update_40222()
 */
#[Group('mukurtu_core')]
class StaleConfigKeyCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'better_exposed_filters',
    'dashboards',
    'facet_bot_blocker',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'layout_builder',
    'layout_discovery',
    'leaflet',
    'media',
    'message',
    'mukurtu_bot_protection',
    'mukurtu_core',
    'node',
    'system',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'views']);
  }

  /**
   * Builds the cleanup service.
   */
  protected function cleanup(): StaleConfigKeyCleanup {
    return new StaleConfigKeyCleanup(\Drupal::entityTypeManager(), \Drupal::configFactory());
  }

  /**
   * Writes config straight to storage, skipping the schema checker.
   */
  protected function writeRaw(string $name, array $data): void {
    \Drupal::service('config.storage')->write($name, $data);
    \Drupal::configFactory()->reset($name);
    \Drupal::entityTypeManager()->clearCachedDefinitions();
  }

  /**
   * Reads config straight from storage.
   */
  protected function readRaw(string $name): array {
    $data = \Drupal::service('config.storage')->read($name);
    $this->assertIsArray($data, "$name exists");
    return $data;
  }

  /**
   * Builds a minimal user view with the given default display options.
   */
  protected function view(string $id, array $display_options): array {
    return [
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => $id,
      'label' => $id,
      'module' => 'views',
      'description' => '',
      'tag' => '',
      'base_table' => 'users_field_data',
      'base_field' => 'uid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => $display_options,
        ],
      ],
    ];
  }

  /**
   * BEF's sort_options_unsupported is removed and the other options kept.
   */
  public function testBefSortOptionsUnsupported(): void {
    $this->writeRaw('views.view.mukurtu_browse', $this->view('mukurtu_browse', [
      'exposed_form' => [
        'type' => 'bef',
        'options' => [
          'submit_button' => 'Apply',
          'bef' => [
            'general' => ['autosubmit' => FALSE],
            'filter' => [
              'name' => [
                'plugin_id' => 'default',
                'advanced' => [
                  'placeholder_text' => '',
                  'sort_options_unsupported' => '',
                ],
              ],
            ],
          ],
        ],
      ],
    ]));

    $this->cleanup()->cleanViews();

    $advanced = $this->readRaw('views.view.mukurtu_browse')['display']['default']['display_options']['exposed_form']['options']['bef']['filter']['name']['advanced'];
    $this->assertArrayNotHasKey('sort_options_unsupported', $advanced);
    $this->assertSame('', $advanced['placeholder_text']);
  }

  /**
   * Timestamp descriptions and unused parent_name keys are removed.
   */
  public function testParagraphViewFieldKeys(): void {
    $this->writeRaw('views.view.mukurtu_all_paragraphs', $this->view('mukurtu_all_paragraphs', [
      'fields' => [
        'created' => [
          'id' => 'created',
          'table' => 'users_field_data',
          'field' => 'created',
          'plugin_id' => 'field',
          'type' => 'timestamp',
          'settings' => [
            'date_format' => 'long',
            'custom_date_format' => '',
            'timezone' => '',
            'tooltip' => ['date_format' => '', 'custom_date_format' => ''],
            'time_diff' => [
              'enabled' => FALSE,
              'future_format' => '@interval hence',
              'past_format' => '@interval ago',
              'granularity' => 2,
              'refresh' => 60,
              'description' => '',
            ],
          ],
        ],
        'parent_name' => [
          'id' => 'parent_name',
          'table' => 'users_field_data',
          'field' => 'parent_name',
          'plugin_id' => 'paragraph_parent_name',
          'label' => 'Parent name',
          'click_sort_column' => 'value',
          'type' => 'string',
          'settings' => [],
          'group_column' => 'value',
          'group_columns' => [],
          'group_rows' => TRUE,
          'delta_limit' => 0,
          'delta_offset' => 0,
          'delta_reversed' => FALSE,
          'delta_first_last' => FALSE,
          'multi_type' => 'separator',
          'separator' => ', ',
          'field_api_classes' => FALSE,
        ],
      ],
    ]));

    $this->cleanup()->cleanViews();

    $fields = $this->readRaw('views.view.mukurtu_all_paragraphs')['display']['default']['display_options']['fields'];
    $this->assertArrayNotHasKey('description', $fields['created']['settings']['time_diff']);
    $this->assertSame(60, $fields['created']['settings']['time_diff']['refresh']);
    $this->assertSame('Parent name', $fields['parent_name']['label']);
    $stale_keys = [
      'click_sort_column',
      'type',
      'settings',
      'group_rows',
      'delta_limit',
      'multi_type',
      'separator',
      'field_api_classes',
    ];
    foreach ($stale_keys as $key) {
      $this->assertArrayNotHasKey($key, $fields['parent_name'], $key);
    }
    // The created field is an entity field, so its keys stay.
    $this->assertSame('timestamp', $fields['created']['type']);
  }

  /**
   * VBO's force_selection_info is converted to the current settings.
   */
  public function testVboForceSelectionInfo(): void {
    $this->writeRaw('views.view.mukurtu_manage_all_content', $this->view('mukurtu_manage_all_content', [
      'fields' => [
        'views_bulk_operations_bulk_form' => [
          'id' => 'views_bulk_operations_bulk_form',
          'table' => 'views',
          'field' => 'views_bulk_operations_bulk_form',
          'plugin_id' => 'views_bulk_operations_bulk_form',
          'batch' => TRUE,
          'force_selection_info' => FALSE,
          'selected_actions' => [],
        ],
      ],
    ]));

    $this->cleanup()->cleanViews();

    $field = $this->readRaw('views.view.mukurtu_manage_all_content')['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form'];
    $this->assertArrayNotHasKey('force_selection_info', $field);
    $this->assertSame('default', $field['show_multipage_selection_box']);
    $this->assertSame('default', $field['show_select_all']);
  }

  /**
   * A site that deleted the views is left alone.
   */
  public function testMissingViews(): void {
    $this->cleanup()->cleanAll();
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('view')->load('mukurtu_browse'));
  }

  /**
   * The facet bot blocker settings lose their dependencies key.
   */
  public function testFacetBotBlockerSettings(): void {
    $this->writeRaw('facet_bot_blocker.settings', [
      'dependencies' => [
        'enforced' => ['module' => ['mukurtu_bot_protection']],
        'module' => ['facet_bot_blocker'],
      ],
      'facets_bot_blocker_limit' => 4,
      'facet_bot_blocker_return_gone' => FALSE,
      'facet_bot_blocker_html' => '<p>Blocked</p>',
    ]);

    $this->cleanup()->cleanFacetBotBlockerSettings();

    $data = $this->readRaw('facet_bot_blocker.settings');
    $this->assertArrayNotHasKey('dependencies', $data);
    $this->assertSame(4, $data['facets_bot_blocker_limit']);
  }

  /**
   * The node reference field loses its user selection handler settings.
   */
  public function testSubmissionReceivedField(): void {
    $this->installEntitySchema('message');
    $this->writeRaw('message.template.mukurtu_submission_received', [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'template' => 'mukurtu_submission_received',
      'label' => 'Submission received',
      'description' => '',
      'text' => [],
      'settings' => [],
    ]);
    FieldStorageConfig::create([
      'field_name' => 'field_item',
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_item',
      'entity_type' => 'message',
      'bundle' => 'mukurtu_submission_received',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => NULL, 'auto_create' => FALSE],
      ],
    ])->save();
    $name = 'field.field.message.mukurtu_submission_received.field_item';
    $data = $this->readRaw($name);
    $data['settings']['handler_settings']['include_anonymous'] = FALSE;
    $data['settings']['handler_settings']['filter'] = ['type' => '_none'];
    $this->writeRaw($name, $data);

    $this->cleanup()->cleanSubmissionReceivedField();

    $handler_settings = $this->readRaw($name)['settings']['handler_settings'];
    $this->assertArrayNotHasKey('include_anonymous', $handler_settings);
    $this->assertArrayNotHasKey('filter', $handler_settings);
    $this->assertFalse($handler_settings['auto_create']);
  }

  /**
   * Empty Layout Builder Restrictions settings are removed from the dashboard.
   */
  public function testDashboardEmptySettings(): void {
    $this->writeRaw('dashboards.dashboard.mukurtu_dashboard', $this->dashboard([
      'allowed_block_categories' => [],
      'entity_view_mode_restriction' => [
        'whitelisted_blocks' => [],
        'blacklisted_blocks' => [],
        'restricted_categories' => [],
        'allowed_layouts' => [],
      ],
    ]));

    $this->cleanup()->cleanDashboard();

    $data = $this->readRaw('dashboards.dashboard.mukurtu_dashboard');
    $this->assertArrayNotHasKey('layout_builder_restrictions', $data['third_party_settings'] ?? []);
    $this->assertSame('Mukurtu Dashboard', $data['admin_label']);
  }

  /**
   * Dashboard restrictions a site has set are left alone.
   */
  public function testDashboardCustomSettings(): void {
    $settings = [
      'allowed_block_categories' => [],
      'entity_view_mode_restriction' => [
        'whitelisted_blocks' => ['Content' => ['system_main_block']],
      ],
    ];
    $this->writeRaw('dashboards.dashboard.mukurtu_dashboard', $this->dashboard($settings));

    $this->cleanup()->cleanDashboard();

    $this->assertSame($settings, $this->readRaw('dashboards.dashboard.mukurtu_dashboard')['third_party_settings']['layout_builder_restrictions']);
  }

  /**
   * Builds the dashboard as an earlier version shipped it.
   */
  protected function dashboard(array $restrictions): array {
    return [
      'uuid' => \Drupal::service('uuid')->generate(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => ['module' => ['layout_builder_restrictions']],
      'third_party_settings' => ['layout_builder_restrictions' => $restrictions],
      'id' => 'mukurtu_dashboard',
      'admin_label' => 'Mukurtu Dashboard',
      'category' => '',
      'frontend' => FALSE,
      'weight' => 0,
      'sections' => [],
    ];
  }

  /**
   * Map formatter settings drop Leaflet widget and 1.x keys and get cast.
   */
  public function testLeafletDisplays(): void {
    NodeType::create(['type' => 'place', 'name' => 'Place'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'type' => 'geofield',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'bundle' => 'place',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'place',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_coverage', [
      'type' => 'mukurtu_leaflet_formatter',
      'settings' => ['include_related_coverage' => TRUE],
    ])->save();

    $name = 'core.entity_view_display.node.place.default';
    $data = $this->readRaw($name);
    $settings = &$data['content']['field_coverage']['settings'];
    $settings['include_related_coverage'] = '1';
    $settings['reset_map'] = ['control' => FALSE, 'position' => 'topright'];
    $settings['geojson_overlays'] = ['sources' => ['fields' => []], 'zoom_to_geojson' => TRUE];
    unset($settings);
    $this->writeRaw($name, $data);

    $this->cleanup()->cleanLeafletDisplays();

    $settings = $this->readRaw($name)['content']['field_coverage']['settings'];
    $this->assertArrayNotHasKey('geojson_overlays', $settings);
    $this->assertArrayNotHasKey('position', $settings['reset_map']);
    $this->assertSame('topright', json_decode($settings['reset_map']['options'], TRUE)['position']);
    $this->assertFalse($settings['reset_map']['control']);
    $this->assertTrue($settings['include_related_coverage']);
  }

  /**
   * Running the cleanup twice changes nothing the second time.
   */
  public function testIsIdempotent(): void {
    $this->testFacetBotBlockerSettings();
    $before = $this->readRaw('facet_bot_blocker.settings');

    $this->cleanup()->cleanAll();

    $this->assertSame($before, $this->readRaw('facet_bot_blocker.settings'));
  }

}
