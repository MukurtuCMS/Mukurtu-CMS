<?php

namespace Drupal\geolocation;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Geocoder Base.
 *
 * @package Drupal\geolocation
 */
abstract class GeocoderBase extends PluginBase implements GeocoderInterface, ContainerFactoryPluginInterface {

  /**
   * Country formatter manager.
   *
   * @var \Drupal\geolocation\GeocoderCountryFormattingManager
   */
  protected $countryFormatterManager;

  /**
   * GoogleGeocoderBase constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\geolocation\GeocoderCountryFormattingManager $geocoder_country_formatter_manager
   *   Country formatter manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderCountryFormattingManager $geocoder_country_formatter_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->countryFormatterManager = $geocoder_country_formatter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder_country_formatting')
    );
  }

  /**
   * Return plugin default settings.
   *
   * @return array
   *   Default settings.
   */
  protected function getDefaultSettings() {
    return [
      'label' => $this->t('Address'),
      'description' => $this->t('Enter an address to be localized.'),
    ];
  }

  /**
   * Return plugin settings.
   *
   * @return array
   *   Settings.
   */
  public function getSettings() {
    return array_replace_recursive($this->getDefaultSettings(), $this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsForm() {
    $settings = $this->getSettings();

    return [
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $settings['label'],
        '#size' => 15,
      ],

      'description' => [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#default_value' => $settings['description'],
        '#size' => 25,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processOptionsForm(array $form_element) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function formAttachGeocoder(array &$render_array, $element_name) {
    $settings = $this->getSettings();

    $render_array['geolocation_geocoder_address'] = [
      '#type' => 'search',
      '#title' => $settings['label'] ?: $this->t('Address'),
      '#placeholder' => $settings['label'] ?: $this->t('Address'),
      '#description' => $settings['description'] ?: $this->t('Enter an address to retrieve location.'),
      '#description_display' => 'after',
      '#maxlength' => 256,
      '#size' => 25,
      '#attributes' => [
        'class' => [
          'geolocation-geocoder-address',
          'form-autocomplete',
        ],
        'data-source-identifier' => $element_name,
      ],
      '#attached' => [
        'drupalSettings' => [
          'geolocation' => [
            'geocoder' => [
              $this->getPluginId() => [
                'inputIds' => [
                  $element_name => $element_name,
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Get formatted address elements from atomics.
   *
   * @param array $address_atomics
   *   Address Atomics.
   *
   * @return array
   *   Address Elements
   */
  protected function addressElements(array $address_atomics) {
    $formatter = $this->countryFormatterManager->getCountry($address_atomics['countryCode'], $this->getPluginId());
    if (empty($formatter)) {
      return $address_atomics;
    }
    return $formatter->format($address_atomics);
  }

  /**
   * {@inheritdoc}
   */
  public function geocode($address) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseGeocode($latitude, $longitude) {
    return NULL;
  }

}
