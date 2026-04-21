<?php

namespace Drupal\config_pages;

/**
 * Class ConfigPagesLoaderService.
 *
 * @package Drupal\config_pages
 */
interface ConfigPagesLoaderServiceInterface {

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
  public function load($type, $context = NULL);

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
  public function getValue($type, $field_name, $deltas = [], $key = NULL);

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
  public function getFieldView($type, $field_name, $view_mode = 'full');

}
