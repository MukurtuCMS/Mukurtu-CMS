<?php

namespace Drupal\geolocation_google_maps;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderCountryFormattingManager;
use Drupal\geolocation\GeocoderInterface;
use Drupal\geolocation\MapProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class.
 *
 * @package Drupal\geolocation_google_places_api
 */
abstract class GoogleGeocoderBase extends GeocoderBase implements GeocoderInterface {

  /**
   * Google maps provider.
   *
   * @var \Drupal\geolocation_google_maps\Plugin\geolocation\MapProvider\GoogleMaps
   */
  protected $googleMapsProvider;

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
   * @param \Drupal\geolocation\MapProviderManager $map_provider_manager
   *   Map provider management.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderCountryFormattingManager $geocoder_country_formatter_manager, MapProviderManager $map_provider_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $geocoder_country_formatter_manager);

    $this->googleMapsProvider = $map_provider_manager->getMapProvider('google_maps');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder_country_formatting'),
      $container->get('plugin.manager.geolocation.mapprovider')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultSettings() {
    $default_settings = parent::getDefaultSettings();

    $default_settings['autocomplete_min_length'] = 1;

    $default_settings['component_restrictions'] = [
      'route' => '',
      'locality' => '',
      'administrative_area' => '',
      'postal_code' => '',
      'country' => '',
    ];

    $default_settings['boundary_restriction'] = [
      'south' => '',
      'west' => '',
      'north' => '',
      'east' => '',
    ];

    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function formAttachGeocoder(array &$render_array, $element_name) {
    parent::formAttachGeocoder($render_array, $element_name);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_google_maps/google',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'geocoder' => [
              $this->getPluginId() => [
                'autocompleteMinLength' => empty($this->configuration['autocomplete_min_length']) ? 1 : (int) $this->configuration['autocomplete_min_length'],
              ],
            ],
          ],
        ],
      ]
    );

    if (!empty($this->configuration['component_restrictions'])) {
      foreach ($this->configuration['component_restrictions'] as $component => $restriction) {
        if (empty($restriction)) {
          continue;
        }

        switch ($component) {
          case 'administrative_area':
            $component = 'administrativeArea';
            break;

          case 'postal_code':
            $component = 'postalCode';
            break;
        }

        $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
          empty($render_array['#attached']) ? [] : $render_array['#attached'],
          [
            'drupalSettings' => [
              'geolocation' => [
                'geocoder' => [
                  $this->getPluginId() => [
                    'componentRestrictions' => [
                      $component => $restriction,
                    ],
                  ],
                ],
              ],
            ],
          ]
        );
      }
    }

    if (!empty($this->configuration['boundary_restriction'])) {
      $bounds = [];
      foreach ($this->configuration['boundary_restriction'] as $key => $value) {
        if (empty($value)) {
          return;
        }
        $bounds[$key] = (float) $value;
      }

      if (!empty($bounds)) {
        $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
          empty($render_array['#attached']) ? [] : $render_array['#attached'],
          [
            'drupalSettings' => [
              'geolocation' => [
                'geocoder' => [
                  $this->getPluginId() => [
                    'bounds' => $bounds,
                  ],
                ],
              ],
            ],
          ]
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsForm() {

    $settings = $this->getSettings();

    $form = parent::getOptionsForm();

    $form['autocomplete_min_length'] = [
      '#title' => $this->t('Autocomplete minimal input length'),
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $settings['autocomplete_min_length'],
    ];

    $form += [
      'component_restrictions' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Component Restrictions'),
        '#description' => $this->t('See <a href="https://developers.google.com/maps/documentation/geocoding/intro#ComponentFiltering">Component Filtering</a>'),
        'route' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['route'],
          '#title' => $this->t('Route'),
          '#size' => 15,
        ],
        'locality' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['locality'],
          '#title' => $this->t('Locality'),
          '#size' => 15,
        ],
        'administrative_area' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['administrative_area'],
          '#title' => $this->t('Administrative Area'),
          '#size' => 15,
        ],
        'postal_code' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['postal_code'],
          '#title' => $this->t('Postal code'),
          '#size' => 5,
        ],
        'country' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['country'],
          '#title' => $this->t('Country'),
          '#description' => $this->t('Enter a comma-separated list to support multiple countries'),
          '#size' => 15,
        ],
      ],
      'boundary_restriction' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Boundary Restriction'),
        '#description' => $this->t('See <a href="https://developers.google.com/maps/documentation/geocoding/intro#Viewports">Viewports</a>'),
        'south' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['south'],
          '#title' => $this->t('South'),
          '#size' => 15,
        ],
        'west' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['west'],
          '#title' => $this->t('West'),
          '#size' => 15,
        ],
        'north' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['north'],
          '#title' => $this->t('North'),
          '#size' => 15,
        ],
        'east' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['east'],
          '#title' => $this->t('East'),
          '#size' => 15,
        ],
      ],
    ];

    return $form;
  }

}
