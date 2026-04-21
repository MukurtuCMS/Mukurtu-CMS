<?php

namespace Drupal\geolocation_address\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\geolocation\GeocoderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AddressWidgetController.
 *
 * @package Drupal\geolocation_address\Controller
 */
class GeocoderController extends ControllerBase {

  /**
   * Geocoder Manager.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.geolocation.geocoder')
    );
  }

  /**
   * Constructs a BlockContent object.
   *
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   Geocoder manager.
   */
  public function __construct(GeocoderManager $geocoder_manager) {
    $this->geocoderManager = $geocoder_manager;
  }

  /**
   * Return coordinates.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Geocoded coordinates.
   */
  public function geocode(Request $request) {
    $geocoder = $this->geocoderManager->getGeocoder($request->get('geocoder'), (array) $request->get('geocoder_settings'));
    $address = $request->get('address');
    $geocoded_result = $geocoder->geocode($address);

    if (!isset($geocoded_result['location'])) {
      return new JsonResponse([]);
    }
    return new JsonResponse($geocoded_result['location']);
  }

  /**
   * Return formatted address data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Formatted address.
   */
  public function reverse(Request $request) {
    $geocoder = $this->geocoderManager->getGeocoder($request->get('geocoder'), (array) $request->get('geocoder_settings'));
    $latitude = (float) $request->get('latitude');
    $longitude = (float) $request->get('longitude');

    $address = $geocoder->reverseGeocode($latitude, $longitude);
    if (empty($address['elements']['countryCode'])) {
      return new JsonResponse(FALSE);
    }

    return new JsonResponse($address['elements']);
  }

}
