<?php

namespace Drupal\config_pages;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\config_pages\Entity\ConfigPages;

/**
 * Class used as loader for ConfigPages.
 *
 * @package Drupal\config_pages
 */
class ConfigPagesLoaderService implements ConfigPagesLoaderServiceInterface {

  /**
   * Constructor.
   */
  public function __construct() {
  }

  /**
   * Loads config page entity by type and context.
   *
   * @param string $type
   *   Config page type to load.
   * @param string $context
   *   Context which should be used to load entity.
   *
   * @return null|\Drupal\config_pages\Entity\ConfigPages
   *   Loaded CP object.
   */
  public function load($type, $context = NULL) {
    $config_page = !empty($type) ? ConfigPages::config($type, $context) : NULL;
    return $config_page;
  }

  /**
   * Get value from CP.
   *
   * @param string|ConfigPages $type
   *   Config page object or type name.
   * @param string $field_name
   *   Field name.
   * @param array|int $deltas
   *   Field value deltas that you like to get.
   * @param string $key
   *   Field "value" key.
   *
   * @return array|mixed|null
   *   Value (or array of values) from specified field in CP.
   */
  public function getValue($type, $field_name, $deltas = [], $key = NULL) {
    $default = !empty($key) ? NULL : [];
    if (!is_array($deltas)) {
      $return_delta = $deltas;
      $deltas = [$deltas];
    }
    else {
      $return_delta = NULL;
    }

    // Exit if empty config page.
    $config_page = is_object($type) ? $type : $this->load($type);
    if (empty($config_page)) {
      return ($return_delta === NULL) ? [] : $default;
    }

    // Load field.
    if ($config_page->hasField($field_name)) {
      $field = $config_page->get($field_name);
    }
    else {
      return ($return_delta === NULL) ? [] : $default;
    }

    // Trim values by deltas.
    $_values = $field->getValue();
    $values = [];
    if (empty($deltas)) {
      $values = $_values;
    }
    else {
      foreach ($deltas as $delta) {
        $values[$delta] = $_values[$delta] ?? [];
      }
    }

    // Extract keys from values.
    if (!empty($key)) {
      foreach ($values as &$value) {
        $value = $value[$key] ?? NULL;
      }
    }

    return ($return_delta === NULL) ? $values : $values[$return_delta];
  }

  /**
   * Get render array of CP.
   *
   * @param string|ConfigPages $type
   *   Config page object or type name.
   * @param string $field_name
   *   Field name you like to get.
   * @param string $view_mode
   *   View mode name.
   *
   * @return array|null
   *   Render array of CP in specified view mode.
   */
  public function getFieldView($type, $field_name, $view_mode = 'full') {
    // Exit if empty config page.
    $config_page = is_object($type) ? $type : $this->load($type);
    if (empty($config_page) || !$config_page->hasField($field_name)) {
      return [
        '#cache' => [
          'tags' => [
            'config_pages_list:' . $type,
          ],
        ],
      ];
    }

    $build = $config_page->get($field_name)->view($view_mode);

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($config_page)
      ->applyTo($build);

    return $build;
  }

}
