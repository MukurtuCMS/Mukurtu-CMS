<?php

namespace Drupal\geolocation_google_places_api\Plugin\geolocation\Geocoder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\KeyProvider;
use Drupal\geolocation_google_maps\GoogleGeocoderBase;
use Drupal\geolocation_google_maps\Plugin\geolocation\MapProvider\GoogleMaps;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides the Google Places API.
 *
 * @Geocoder(
 *   id = "google_places_api",
 *   name = @Translation("Google Places API"),
 *   description = @Translation("Attention: This Plugin needs you to follow Google Places API TOS and either use the Attribution Block or provide it yourself."),
 *   locationCapable = true,
 *   boundaryCapable = true,
 *   frontendCapable = true,
 *   reverseCapable = false,
 * )
 */
class GooglePlacesAPI extends GoogleGeocoderBase {

  /**
   * {@inheritdoc}
   */
  public function formAttachGeocoder(array &$render_array, $element_name) {
    parent::formAttachGeocoder($render_array, $element_name);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      $render_array['#attached'],
      [
        'library' => [
          'geolocation_google_places_api/geolocation_google_places_api.geocoder.googleplacesapi',
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

    $config = \Drupal::config('geolocation_google_maps.settings');

    $request_url = GoogleMaps::$googleMapsApiUrlBase;
    if ($config->get('china_mode')) {
      $request_url = GoogleMaps::$googleMapsApiUrlBaseChina;
    }
    $request_url .= '/maps/api/place/autocomplete/json?input=' . $address;

    $google_key = '';

    if (!empty($config->get('google_map_api_server_key'))) {
      $google_key = KeyProvider::getKeyValue($config->get('google_map_api_server_key'));
    }
    elseif (!empty($config->get('google_map_api_key'))) {
      $google_key = KeyProvider::getKeyValue($config->get('google_map_api_key'));
    }

    if (!empty($google_key)) {
      $request_url .= '&key=' . $google_key;
    }
    if (!empty($this->configuration['component_restrictions']['country'])) {
      $data = explode(',', $this->configuration['component_restrictions']['country']);
      if (is_array($data)) {
        foreach ($data as $country) {
          $request_url .= '&components[]=country:' . $country;
        }
      }
      else {
        $request_url .= '&components=country:' . $this->configuration['component_restrictions']['country'];
      }
    }
    if (!empty($config->get('google_map_custom_url_parameters')['language'])) {
      $request_url .= '&language=' . $config->get('google_map_custom_url_parameters')['language'];
    }

    try {
      $result = Json::decode(\Drupal::httpClient()->request('GET', $request_url)->getBody());
    }
    catch (RequestException $e) {
      \Drupal::logger('geolocation')->warning($e->getMessage());
      return FALSE;
    }

    if (
      $result['status'] != 'OK'
      || empty($result['predictions'][0]['place_id'])
    ) {
      return FALSE;
    }

    try {
      if (!empty($config->get('google_maps_base_url'))) {
        $details_url = $config->get('google_maps_base_url');
      }
      elseif ($config->get('china_mode')) {
        $details_url = GoogleMaps::$googleMapsApiUrlBaseChina;
      }
      else {
        $details_url = GoogleMaps::$googleMapsApiUrlBase;
      }

      $details_url .= '/maps/api/place/details/json?placeid=' . $result['predictions'][0]['place_id'];

      if (!empty($google_key)) {
        $details_url .= '&key=' . $google_key;
      }
      $details = Json::decode(\Drupal::httpClient()->request('GET', $details_url)->getBody());

    }
    catch (RequestException $e) {
      \Drupal::logger('geolocation')->warning($e->getMessage());
      return FALSE;
    }

    if (
      $details['status'] != 'OK'
      || empty($details['result']['geometry']['location'])
    ) {
      return FALSE;
    }

    return [
      'location' => [
        'lat' => $details['result']['geometry']['location']['lat'],
        'lng' => $details['result']['geometry']['location']['lng'],
      ],
      // @todo Add viewport or build it if missing.
      'boundary' => [
        'lat_north_east' => empty($details['result']['geometry']['viewport']) ? $details['result']['geometry']['location']['lat'] + 0.005 : $details['result']['geometry']['viewport']['northeast']['lat'],
        'lng_north_east' => empty($details['result']['geometry']['viewport']) ? $details['result']['geometry']['location']['lng'] + 0.005 : $details['result']['geometry']['viewport']['northeast']['lng'],
        'lat_south_west' => empty($details['result']['geometry']['viewport']) ? $details['result']['geometry']['location']['lat'] - 0.005 : $details['result']['geometry']['viewport']['southwest']['lat'],
        'lng_south_west' => empty($details['result']['geometry']['viewport']) ? $details['result']['geometry']['location']['lng'] - 0.005 : $details['result']['geometry']['viewport']['southwest']['lng'],
      ],
      'address' => empty($details['result']['formatted_address']) ? '' : $details['result']['formatted_address'],
    ];
  }

}
