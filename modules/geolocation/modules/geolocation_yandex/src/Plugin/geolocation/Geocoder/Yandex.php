<?php

namespace Drupal\geolocation_yandex\Plugin\geolocation\Geocoder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderInterface;
use Drupal\geolocation_yandex\Plugin\geolocation\MapProvider\Yandex as YandexMapProvider;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides the Yandex.
 *
 * @Geocoder(
 *   id = "yandex",
 *   name = @Translation("Yandex"),
 *   description = @Translation("See https://tech.yandex.com/maps/doc/geocoder/desc/concepts/about-docpage/ for details."),
 *   locationCapable = true,
 *   boundaryCapable = true,
 *   frontendCapable = true,
 * )
 */
class Yandex extends GeocoderBase implements GeocoderInterface {

  /**
   * {@inheritdoc}
   */
  public function formAttachGeocoder(array &$render_array, $element_name) {
    parent::formAttachGeocoder($render_array, $element_name);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      empty($render_array['#attached']) ? [] : $render_array['#attached'],
      [
        'drupalSettings' => [
          'geolocation' => [
            'geocoder' => [
              $this->getPluginId() => [],
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

    $config = \Drupal::config('geolocation_yandex.settings');

    $url = Url::fromUri('https://geocode-maps.yandex.ru/1.x/', [
      'query' => [
        'geocode' => $address,
        'format' => 'json',
        'apikey' => $config->get('api_key'),
        'lang' => YandexMapProvider::getApiUrlLangcode(),
      ],
    ]);

    try {
      $result = Json::decode(\Drupal::httpClient()->get($url->toString())->getBody());
    }
    catch (RequestException $e) {
      \Drupal::logger('geolocation')->warning($e->getMessage());
      return FALSE;
    }

    $location = [];

    if (empty($result['response']['GeoObjectCollection']['featureMember'][0])) {
      return FALSE;
    }
    else {
      $coordinates = explode(' ', $result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos']);
      $location['location'] = [
        'lng' => $coordinates[0],
        'lat' => $coordinates[1],
      ];
    }

    if (!empty($result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['boundedBy']['Envelope'])) {
      $lowerCoordinates = explode(' ', $result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['boundedBy']['Envelope']['lowerCorner']);
      $upperCoordinates = explode(' ', $result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['boundedBy']['Envelope']['upperCorner']);
      $location['boundary'] = [
        'lat_north_east' => $upperCoordinates[0],
        'lng_north_east' => $upperCoordinates[1],
        'lat_south_west' => $lowerCoordinates[0],
        'lng_south_west' => $lowerCoordinates[1],
      ];
    }

    if (!empty($result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['description'])) {
      $location['address'] = $result['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['description'];
    }

    return $location;
  }

}
