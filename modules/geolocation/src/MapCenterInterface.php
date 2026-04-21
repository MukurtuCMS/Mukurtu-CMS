<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation MapCenter plugins.
 */
interface MapCenterInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default map settings.
   */
  public static function getDefaultSettings();

  /**
   * Provide MapCenter option specific settings.
   *
   * @param array $settings
   *   Current settings.
   *
   * @return array
   *   An array only containing keys defined in this plugin.
   */
  public function getSettings(array $settings);

  /**
   * Settings form by ID and context.
   *
   * @param int $center_option_id
   *   MapCenter option ID.
   * @param array $settings
   *   The current option settings.
   * @param mixed $context
   *   Current context.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm($center_option_id, array $settings, $context = NULL);

  /**
   * For one MapCenter (i.e. boundary filter), return all options (all filters).
   *
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Available center options indexed by ID.
   */
  public function getAvailableMapCenterOptions($context);

  /**
   * Alter map..
   *
   * @param array $map
   *   Map object.
   * @param int $center_option_id
   *   MapCenter option ID.
   * @param array $center_option_settings
   *   The current feature settings.
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Map object.
   */
  public function alterMap(array $map, $center_option_id, array $center_option_settings, $context = NULL);

}
