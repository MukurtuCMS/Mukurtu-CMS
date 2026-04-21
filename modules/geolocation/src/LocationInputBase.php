<?php

namespace Drupal\geolocation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LocationInput Base.
 *
 * @package Drupal\geolocation
 */
abstract class LocationInputBase extends PluginBase implements LocationInputInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings) {
    $default_settings = (array) $this->getDefaultSettings();
    $settings = array_replace_recursive($default_settings, $settings);

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm($option_id = NULL, array $settings = [], $context = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationInputOptions($context) {
    return [
      $this->getPluginId() => $this->getPluginDefinition()['name'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates($form_value, $option_id, array $option_settings, $context = NULL) {
    if (
      empty($form_value['coordinates'])
      || !is_array($form_value['coordinates'])
      || !isset($form_value['coordinates']['lat'])
      || !isset($form_value['coordinates']['lng'])
      || $form_value['coordinates']['lng'] === ''
      || $form_value['coordinates']['lng'] === ''
    ) {
      return FALSE;
    }

    return [
      'lat' => (float) $form_value['coordinates']['lat'],
      'lng' => (float) $form_value['coordinates']['lng'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(string $center_option_id, array $center_option_settings, $context = NULL, array $default_value = NULL) {
    $form['coordinates'] = [
      '#type' => 'geolocation_input',
      '#title' => $this->t('Coordinates'),
    ];
    if (!empty($default_value)) {
      $form['coordinates']['#default_value'] = $default_value;
    }

    return $form;
  }

}
