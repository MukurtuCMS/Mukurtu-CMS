<?php

namespace Drupal\geocoder\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geocoder\DumperPluginManager;
use Drupal\geocoder\DumperInterface;
use Drupal\geocoder\FormatterPluginManager;
use Drupal\geocoder\Geocoder;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a GeocoderApiEnpoints object.
 */
class GeocoderApiEnpoints extends ControllerBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Geocoder Service.
   *
   * @var \Drupal\geocoder\Geocoder
   */
  protected $geocoder;

  /**
   * The dumper plugin manager service.
   *
   * @var \Drupal\geocoder\DumperPluginManager
   */
  protected $dumperPluginManager;

  /**
   * The Geocoder formatter plugin manager service.
   *
   * @var \Drupal\geocoder\FormatterPluginManager
   */
  protected $geocoderFormatterPluginManager;

  /**
   * The Response.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  protected $response;

  /**
   * Get the Address Formatter.
   *
   * @param string|null $address_format
   *   The Address Format string identifier, is set.
   */
  protected function getAddressFormatter(?string $address_format = NULL): string {
    return $address_format ?: 'default_formatted_address';
  }

  /**
   * Set Geocoders Options.
   *
   * Merges Geocoders Options from Request Query and Module Configurations.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   *
   * @return array
   *   The merged Plugins Options array.
   */
  protected function setGeocodersOptions(Request $request): array {
    // Retrieve geocoders options from the module configurations.
    $geocoders_configs = $this->config->get('plugins_options') ?: [];

    // Get possible query string specific geocoders options.
    $geocoders_options = (array) ($request->query->all()['options'] ?? []);

    // Return merged geocoders options.
    return NestedArray::mergeDeep($geocoders_configs, $geocoders_options);
  }

  /**
   * Add a geometry property if not defined (as Google Maps Geocoding does).
   *
   * @param \Geocoder\Model\Address $address
   *   The Address array.
   *
   * @return array
   *   The Address Geometry Property.
   */
  protected function addGeometryProperty(Address $address) {
    $address_array = $address->toArray();

    return [
      'location' => [
        'lat' => $address_array['latitude'],
        'lng' => $address_array['longitude'],
      ],
      'viewport' => [
        'northeast' => [
          'lat' => $address_array['bounds']['north'],
          'lng' => $address_array['bounds']['east'],
        ],
        'southwest' => [
          'lat' => $address_array['bounds']['south'],
          'lng' => $address_array['bounds']['west'],
        ],
      ],
    ];
  }

  /**
   * Get Address Collection Response.
   *
   * @param \Geocoder\Model\AddressCollection $geo_collection
   *   The Address Collection.
   * @param \Drupal\geocoder\DumperInterface|null $dumper
   *   The Dumper or null.
   * @param string|null $address_format
   *   The specific @GeocoderFormatter id to be used.
   */
  protected function getAddressCollectionResponse(AddressCollection $geo_collection, ?DumperInterface $dumper = NULL, ?string $address_format = NULL): void {
    $result = [];
    /** @var \Geocoder\Model\Address $geo_address **/
    foreach ($geo_collection->all() as $k => $geo_address) {
      if (isset($dumper)) {
        $result[$k] = $dumper->dump($geo_address);
      }
      else {
        $result[$k] = $geo_address->toArray();
        // If a formatted_address property is not defined (as Google Maps
        // Geocoding does), then create it with our own formatter.
        if (!isset($result[$k]['formatted_address'])) {

          try {
            $result[$k]['formatted_address'] = $this->geocoderFormatterPluginManager->createInstance($this->getAddressFormatter($address_format))
              ->format($geo_address);
          }
          catch (\Exception $e) {
            $this->getLogger('geocoder')->error($e->getMessage());
          }
        }
        // If a geometry property is not defined
        // (as Google Maps Geocoding does), then create it with our own dumper.
        if (!isset($result[$k]['geometry'])) {
          $result[$k]['geometry'] = $this->addGeometryProperty($geo_address);
        }
      }
    }
    $this->response = new CacheableJsonResponse($result, 200);
    $this->response->addCacheableDependency(CacheableMetadata::createFromObject($result));
  }

  /**
   * Get the output Dumper Format.
   *
   * @param string $format
   *   The Dumper Format.
   *
   * @return object|null
   *   The Dumper object or Null.
   */
  protected function getDumper($format) {
    $dumper = NULL;
    if (!empty($format)) {
      try {
        $dumper = $this->dumperPluginManager->createInstance($format);
      }
      catch (\Exception $e) {
        $this->getLogger('geocoder')->error($e->getMessage());
      }
    }
    return $dumper;
  }

  /**
   * Constructs a new GeofieldMapGeocoder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\geocoder\Geocoder $geocoder
   *   The Geocoder service.
   * @param \Drupal\geocoder\DumperPluginManager $dumper_plugin_manager
   *   The dumper plugin manager service.
   * @param \Drupal\geocoder\FormatterPluginManager $geocoder_formatter_plugin_manager
   *   The geocoder formatter plugin manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Geocoder $geocoder,
    DumperPluginManager $dumper_plugin_manager,
    FormatterPluginManager $geocoder_formatter_plugin_manager,
  ) {
    $this->config = $config_factory->get('geocoder.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->geocoder = $geocoder;
    $this->dumperPluginManager = $dumper_plugin_manager;
    $this->geocoderFormatterPluginManager = $geocoder_formatter_plugin_manager;
    // Define a default empty Response as 204 No Content.
    $this->response = new Response('', 204);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('geocoder'),
      $container->get('plugin.manager.geocoder.dumper'),
      $container->get('plugin.manager.geocoder.formatter')
    );
  }

  /**
   * Process a Geocode operation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to geocode.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The geocode response.
   */
  public function geocode(Request $request) {
    $address = $request->query->get('address');
    $geocoders_ids = $request->query->get('geocoder');
    $format = $request->query->get('format');

    try {
      $geocoders = $this->entityTypeManager->getStorage('geocoder_provider')
        ->loadMultiple(explode(',', str_replace(' ', '', $geocoders_ids)));
      $address_format = $request->query->get('address_format');

      if (isset($address)) {
        $dumper = $this->getDumper($format);
        $geo_collection = $this->geocoder->geocode($address, $geocoders);
        if ($geo_collection instanceof AddressCollection) {
          $this->getAddressCollectionResponse($geo_collection, $dumper, $address_format);
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
    return $this->response;
  }

  /**
   * Process a Reverse Geocode.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to reverse geocode.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The reverse geocode response.
   */
  public function reverseGeocode(Request $request) {

    $latlng = $request->query->get('latlng');
    $geocoders_ids = $request->query->get('geocoder');
    $format = $request->query->get('format');

    try {
      $geocoders = $this->entityTypeManager->getStorage('geocoder_provider')
        ->loadMultiple(explode(',', $geocoders_ids));
      if (isset($latlng)) {
        $latlng = explode(',', $request->query->get('latlng'));
        $dumper = $this->getDumper($format);
        $geo_collection = $this->geocoder->reverse($latlng[0], $latlng[1], $geocoders);
        if ($geo_collection instanceof AddressCollection) {
          $this->getAddressCollectionResponse($geo_collection, $dumper);
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('geocoder')->error($e->getMessage());
    }
    return $this->response;
  }

}
