<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Removes config keys that earlier versions shipped but nothing reads.
 *
 * These keys came in with config exported from older contrib versions or
 * copied between plugins. They do nothing, but they fail strict config
 * schema checks (#2386). Each step only removes keys or casts stored values
 * to their schema types; it never changes a value a site could have
 * customized.
 */
final class StaleConfigKeyCleanup {

  /**
   * Views whose exposed form carried BEF's sort_options_unsupported key.
   */
  private const BEF_VIEWS = [
    'mukurtu_browse',
    'mukurtu_digital_heritage_browse',
    'mukurtu_dictionary',
  ];

  /**
   * Views whose timestamp fields carried a time_diff description.
   */
  private const TIME_DIFF_VIEWS = [
    'mukurtu_all_paragraphs',
    'mukurtu_import_results_multipage_items',
  ];

  /**
   * Entity field formatter keys that paragraph_parent_name doesn't use.
   */
  private const ENTITY_FIELD_KEYS = [
    'click_sort_column',
    'type',
    'settings',
    'group_column',
    'group_columns',
    'group_rows',
    'delta_limit',
    'delta_offset',
    'delta_reversed',
    'delta_first_last',
    'multi_type',
    'separator',
    'field_api_classes',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Runs every cleanup step.
   */
  public function cleanAll(): void {
    $this->cleanViews();
    $this->cleanFacetBotBlockerSettings();
    $this->cleanSubmissionReceivedField();
    $this->cleanDashboard();
    $this->cleanLeafletDisplays();
    $this->castThumbnailSettings();
  }

  /**
   * Cleans the views that shipped stale keys.
   */
  public function cleanViews(): void {
    $storage = $this->entityTypeManager->getStorage('view');
    $ids = array_merge(self::BEF_VIEWS, self::TIME_DIFF_VIEWS, ['mukurtu_manage_all_content']);
    foreach ($storage->loadMultiple($ids) as $view) {
      $displays = $view->get('display');
      $changed = FALSE;
      foreach ($displays as &$display) {
        if (!isset($display['display_options'])) {
          continue;
        }
        $options = &$display['display_options'];

        foreach ($options['exposed_form']['options']['bef']['filter'] ?? [] as $filter_id => $filter) {
          if (isset($filter['advanced']) && array_key_exists('sort_options_unsupported', $filter['advanced'])) {
            unset($options['exposed_form']['options']['bef']['filter'][$filter_id]['advanced']['sort_options_unsupported']);
            $changed = TRUE;
          }
        }

        foreach ($options['fields'] ?? [] as $field_id => $field) {
          $field_options = &$options['fields'][$field_id];
          if (isset($field['settings']['time_diff']) && array_key_exists('description', $field['settings']['time_diff'])) {
            unset($field_options['settings']['time_diff']['description']);
            $changed = TRUE;
          }
          if (($field['plugin_id'] ?? NULL) === 'paragraph_parent_name') {
            foreach (self::ENTITY_FIELD_KEYS as $key) {
              if (array_key_exists($key, $field_options)) {
                unset($field_options[$key]);
                $changed = TRUE;
              }
            }
          }
          if (($field['plugin_id'] ?? NULL) === 'views_bulk_operations_bulk_form' && array_key_exists('force_selection_info', $field)) {
            // Same conversion as views_bulk_operations_update_8036(), with
            // the values today's VBO schema accepts.
            $show = match ($field['force_selection_info']) {
              TRUE, 1, '1', 'show' => 'always_show',
              'hide' => 'always_hide',
              default => 'default',
            };
            $field_options += [
              'show_multipage_selection_box' => $show,
              'show_select_all' => $show,
            ];
            unset($field_options['force_selection_info']);
            $changed = TRUE;
          }
          unset($field_options);
        }
        unset($options);
      }
      unset($display);
      if ($changed) {
        $view->set('display', $displays)->save();
      }
    }
  }

  /**
   * Removes dependencies from facet_bot_blocker.settings.
   *
   * Simple config has no dependencies, so the key was never used.
   */
  public function cleanFacetBotBlockerSettings(): void {
    $config = $this->configFactory->getEditable('facet_bot_blocker.settings');
    if (!$config->isNew() && $config->get('dependencies') !== NULL) {
      $config->clear('dependencies')->save(TRUE);
    }
  }

  /**
   * Removes user selection handler settings from a node reference field.
   */
  public function cleanSubmissionReceivedField(): void {
    $field = $this->entityTypeManager->getStorage('field_config')->load('message.mukurtu_submission_received.field_item');
    if (!$field || !str_starts_with((string) $field->getSetting('handler'), 'default:node')) {
      return;
    }
    $handler_settings = $field->getSetting('handler_settings') ?? [];
    if (!array_key_exists('include_anonymous', $handler_settings) && !array_key_exists('filter', $handler_settings)) {
      return;
    }
    unset($handler_settings['include_anonymous'], $handler_settings['filter']);
    $field->setSetting('handler_settings', $handler_settings)->save();
  }

  /**
   * Removes empty Layout Builder Restrictions settings from the dashboard.
   *
   * They used key names that layout_builder_restrictions no longer reads.
   * Settings with any value in them are left alone.
   */
  public function cleanDashboard(): void {
    if (!$this->entityTypeManager->hasDefinition('dashboard')) {
      return;
    }
    $dashboard = $this->entityTypeManager->getStorage('dashboard')->load('mukurtu_dashboard');
    if (!$dashboard) {
      return;
    }
    $settings = $dashboard->getThirdPartySettings('layout_builder_restrictions');
    if (!$settings || !self::isEmptyRecursive($settings)) {
      return;
    }
    foreach (array_keys($settings) as $key) {
      $dashboard->unsetThirdPartySetting('layout_builder_restrictions', $key);
    }
    $dashboard->save();
  }

  /**
   * Cleans map formatter settings on node displays.
   *
   * Drops geojson_overlays, which only the Leaflet widget uses, and replaces
   * the Leaflet 1.x reset_map position with the options string current
   * Leaflet reads, keeping the position. Every display that uses the map
   * formatter is saved, because its settings were stored before they had a
   * schema, so values such as include_related_coverage: '1' were never cast
   * to their types. Saving casts them.
   */
  public function cleanLeafletDisplays(): void {
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    $ids = $storage->getQuery()->condition('targetEntityType', 'node')->execute();
    foreach ($storage->loadMultiple($ids) as $display) {
      $uses_map = FALSE;
      foreach ($display->getComponents() as $name => $component) {
        if (($component['type'] ?? NULL) !== 'mukurtu_leaflet_formatter') {
          continue;
        }
        $uses_map = TRUE;
        $settings = &$component['settings'];
        unset($settings['geojson_overlays']);
        if (isset($settings['reset_map']) && array_key_exists('position', $settings['reset_map'])) {
          $settings['reset_map']['options'] ??= json_encode([
            'position' => $settings['reset_map']['position'],
            'title' => 'Reset View',
          ]);
          unset($settings['reset_map']['position']);
        }
        unset($settings);
        $display->setComponent($name, $component);
      }
      if ($uses_map) {
        $display->save();
      }
    }
  }

  /**
   * Re-saves the default thumbnail settings so their file IDs are cast.
   *
   * Before the settings had a schema, mukurtu_media's installer stored them
   * as strings and the settings form as integers.
   */
  public function castThumbnailSettings(): void {
    $config = $this->configFactory->getEditable('mukurtu_thumbnail.settings');
    if (!$config->isNew()) {
      $config->save();
    }
  }

  /**
   * Whether an array holds nothing but empty arrays.
   */
  private static function isEmptyRecursive(array $values): bool {
    foreach ($values as $value) {
      if (is_array($value) ? !self::isEmptyRecursive($value) : !empty($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
