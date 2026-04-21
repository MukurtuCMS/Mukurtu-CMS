<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation Location plugins.
 */
interface LocationInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default map settings.
   */
  public static function getDefaultSettings();

  /**
   * Provide Location option specific settings.
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
   * @param int $location_option_id
   *   Location option ID.
   * @param array $settings
   *   The current option settings.
   * @param mixed $context
   *   Current context.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm($location_option_id, array $settings, $context = NULL);

  /**
   * For one Location (i.e. boundary filter), return all options (all filters).
   *
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Available location options indexed by ID.
   */
  public function getAvailableLocationOptions($context);

  /**
   * Get map location.
   *
   * @param int $location_option_id
   *   Location option ID.
   * @param array $location_option_settings
   *   The current feature settings.
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   With content
   *    'lat' => latitude
   *    'lng' => longitude
   */
  public function getCoordinates($location_option_id, array $location_option_settings, $context = NULL);

}
