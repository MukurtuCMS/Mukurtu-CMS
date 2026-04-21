<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation LocationInput plugins.
 */
interface LocationInputInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default plugin settings.
   */
  public static function getDefaultSettings();

  /**
   * Provide LocationInput option specific settings.
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
   *   LocationInput option ID.
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
   * For one LocationInput (i.e. boundary filter), return all options.
   *
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Available center options indexed by ID.
   */
  public function getAvailableLocationInputOptions($context);

  /**
   * Get center value.
   *
   * @param mixed $form_value
   *   Form value.
   * @param int $center_option_id
   *   LocationInput option ID.
   * @param array $center_option_settings
   *   The current feature settings.
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Render array.
   */
  public function getCoordinates($form_value, $center_option_id, array $center_option_settings, $context = NULL);

  /**
   * Get center form.
   *
   * @param string $center_option_id
   *   LocationInput option ID.
   * @param array $center_option_settings
   *   The current feature settings.
   * @param mixed $context
   *   Context like field formatter, field widget or view.
   * @param array|null $default_value
   *   Optional form values.
   *
   * @return array
   *   Form.
   */
  public function getForm(string $center_option_id, array $center_option_settings, $context = NULL, array $default_value = NULL);

}
