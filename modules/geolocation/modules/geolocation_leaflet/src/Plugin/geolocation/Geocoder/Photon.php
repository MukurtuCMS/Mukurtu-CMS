<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\Geocoder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides the Photon.
 *
 * @Geocoder(
 *   id = "photon",
 *   name = @Translation("Photon"),
 *   description = @Translation("See https://photon.komoot.io for details."),
 *   locationCapable = true,
 *   boundaryCapable = true,
 *   frontendCapable = true,
 *   reverseCapable = true,
 * )
 */
class Photon extends GeocoderBase implements GeocoderInterface {

  /**
   * Base URL.
   *
   * @var string
   *   Photon URL.
   */
  public $requestBaseUrl = 'https://photon.komoot.io';

  /**
   * {@inheritdoc}
   */
  protected function getDefaultSettings() {
    $default_settings = parent::getDefaultSettings();

    $default_settings['autocomplete_min_length'] = 1;

    $default_settings['location_priority'] = [
      'lat' => '',
      'lng' => '',
    ];

    $default_settings['remove_duplicates'] = FALSE;

    return $default_settings;
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

    $form['location_priority'] = [
      '#type' => 'geolocation_input',
      '#title' => $this->t('Location Priority'),
      '#default_value' => [
        'lat' => $settings['location_priority']['lat'],
        'lng' => $settings['location_priority']['lng'],
      ],
    ];

    $form['remove_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove duplicates from the Photon API'),
      '#default_value' => $settings['remove_duplicates'],
      '#description' => $this->t('The Photon API can generate duplicates for some locations (i.e. cities that are states for example), this option will remove them.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formAttachGeocoder(array &$render_array, $element_name) {
    parent::formAttachGeocoder($render_array, $element_name);

    $settings = $this->getSettings();

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'library' => [
          'geolocation_leaflet/geocoder.photon',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'geocoder' => [
              $this->getPluginId() => [
                'autocompleteMinLength' => empty($this->configuration['autocomplete_min_length']) ? 1 : (int) $this->configuration['autocomplete_min_length'],
                'locationPriority' => [
                  'lat' => $settings['location_priority']['lat'],
                  'lon' => $settings['location_priority']['lng'],
                ],
                'removeDuplicates' => $settings['remove_duplicates'],
              ],
            ],
          ],
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function geocode($address) {
    if (empty($address)) {
      return FALSE;
    }

    $options = [
      'q' => $address,
      'limit' => 1,
    ];

    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if (in_array($lang, ['de', 'en', 'fr'])) {
      $options['lang'] = $lang;
    }

    $url = Url::fromUri($this->requestBaseUrl . '/api/', [
      'query' => $options,
    ]);

    try {
      $result = Json::decode(\Drupal::httpClient()->get($url->toString())->getBody());
    }
    catch (RequestException $e) {
      \Drupal::logger('geolocation')->warning($e->getMessage());
      return FALSE;
    }

    $location = [];

    if (empty($result['features'][0])) {
      return FALSE;
    }
    else {
      $location['location'] = [
        'lat' => $result['features'][0]['geometry']['coordinates'][1],
        'lng' => $result['features'][0]['geometry']['coordinates'][0],
      ];
    }

    if (!empty($result[0]['boundingbox'])) {
      $location['boundary'] = [
        'lat_north_east' => $result[0]['boundingbox'][1],
        'lng_north_east' => $result[0]['boundingbox'][3],
        'lat_south_west' => $result[0]['boundingbox'][0],
        'lng_south_west' => $result[0]['boundingbox'][2],
      ];
    }

    if (!empty($result[0]['display_name'])) {
      $location['address'] = $result[0]['display_name'];
    }

    return $location;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseGeocode($latitude, $longitude) {
    $url = Url::fromUri($this->requestBaseUrl . '/reverse', [
      'query' => [
        'lat' => $latitude,
        'lon' => $longitude,
        'limit' => 20,
      ],
    ]);

    try {
      $result = Json::decode(\Drupal::httpClient()->get($url->toString())->getBody());
    }
    catch (RequestException $e) {
      \Drupal::logger('geolocation')->warning($e->getMessage());
      return FALSE;
    }

    if (empty($result['features'][0]['properties'])) {
      return FALSE;
    }

    $countries = \Drupal::service('address.country_repository')->getList();
    $address_atomics = [];
    foreach ($result['features'] as $id => $entry) {
      if (empty($entry['properties']['osm_type'])) {
        continue;
      }

      switch ($entry['properties']['osm_type']) {
        case 'N':
          $address_atomics = [
            'houseNumber' => !empty($entry['properties']['housenumber']) ? $entry['properties']['housenumber'] : '',
            'road' => $entry['properties']['street'],
            'city' => $entry['properties']['city'],
            'postcode' => $entry['properties']['postcode'],
            'state' => $entry['properties']['state'],
            'country' => $entry['properties']['country'],
            'countryCode' => array_search($entry['properties']['country'], $countries),
            'county' => $entry['properties']['county'],
          ];
          break 2;
      }
    }

    if (empty($address_atomics)) {
      return FALSE;
    }

    return [
      'atomics' => $address_atomics,
      'elements' => $this->addressElements($address_atomics),
      'formatted_address' => empty($result['display_name']) ? '' : $result['display_name'],
    ];
  }

}
