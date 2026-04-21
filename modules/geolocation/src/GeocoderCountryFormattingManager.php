<?php

namespace Drupal\geolocation;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Search plugin manager.
 */
class GeocoderCountryFormattingManager extends DefaultPluginManager {

  /**
   * Constructs an GeocoderCountryFormattingManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/geolocation/GeocoderCountryFormatting', $namespaces, $module_handler, 'Drupal\geolocation\GeocoderCountryFormattingInterface', 'Drupal\geolocation\Annotation\GeocoderCountryFormatting');
    $this->alterInfo('geolocation_geocoder_country_formatting_info');
    $this->setCacheBackend($cache_backend, 'geolocation_geocoder_country_formatting');
  }

  /**
   * Return Country plugin by country code.
   *
   * @param string $country_code
   *   Plugin ID.
   * @param string $geocoder_id
   *   Geocoder ID.
   *
   * @return \Drupal\geolocation\GeocoderCountryFormattingInterface
   *   Geocoder instance.
   */
  public function getCountry($country_code, $geocoder_id) {
    $country_code = strtolower($country_code);

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if (isset($definition['country_code']) && $definition['country_code'] == $country_code && $definition['geocoder'] == $geocoder_id) {
        $instance = $this->createInstance($plugin_id);
        break;
      }
    }

    if (
      empty($instance)
      && $this->hasDefinition($geocoder_id . '_standard')
    ) {
      $instance = $this->createInstance($geocoder_id . '_standard');
    }

    /** @var \Drupal\geolocation\GeocoderCountryFormattingInterface $instance */
    return $instance;
  }

}
