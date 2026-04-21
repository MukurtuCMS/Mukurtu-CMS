<?php

declare(strict_types=1);

namespace Drupal\geocoder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\geocoder\Entity\GeocoderProvider;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;

/**
 * Provides a geocoder factory class.
 */
class Geocoder implements GeocoderInterface {

  use LoggerChannelTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The geocoder provider plugin manager service.
   *
   * @var \Drupal\geocoder\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a geocoder factory class.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\geocoder\ProviderPluginManager $provider_plugin_manager
   *   The geocoder provider plugin manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ProviderPluginManager $provider_plugin_manager, ModuleHandlerInterface $module_handler) {
    $this->config = $config_factory->get('geocoder.settings');
    $this->providerPluginManager = $provider_plugin_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function geocode(GeocodeQuery|string $address, array $providers) {
    // Allow other modules to adjust the address.
    if (is_string($address)) {
      $this->moduleHandler->alter('geocode_address_string', $address);
    }
    else {
      $this->moduleHandler->alter('geocode_address_geocode_query', $address);
    }

    /** @var \Drupal\geocoder\GeocoderProviderInterface $provider */
    foreach ($providers as $provider) {
      try {
        // Manage a side case in which the provider is still coming by 2.x
        // branch as string.
        // @see: https://www.drupal.org/project/geocoder/issues/3202941
        if (is_string($provider)) {
          $provider_id = $provider;
          $provider = GeocoderProvider::load($provider);
          if (!$provider instanceof GeocoderProviderInterface) {
            throw new \Exception(sprintf("Unable to define a GeocoderProvider from string '%s'", $provider_id));
          }
        }

        if (is_string($address)) {
          $result = $provider->getPlugin()->geocode($address);
        }
        elseif ($provider->getPlugin() instanceof ProviderGeocoderPhpInterface) {
          $result = $provider->getPlugin()->geocodeQuery($address);
        }

        if (!isset($result) || $result->isEmpty()) {
          throw new \Exception(sprintf('Unable to geocode "%s" with the %s provider.', $address, $provider->id()));
        }
        return $result;
      }
      catch (\Exception $e) {
        $this->getLogger('geocoder')->warning($e->getMessage());
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function reverse(string $latitude, string $longitude, array $providers): ?AddressCollection {
    // Allow other modules to adjust the coordinates.
    $this->moduleHandler->alter('reverse_geocode_coordinates', $latitude, $longitude);

    /** @var \Drupal\geocoder\GeocoderProviderInterface $provider */
    foreach ($providers as $provider) {
      try {
        // Manage a side case in which the provider is still coming by 2.x
        // branch as string.
        // @see: https://www.drupal.org/project/geocoder/issues/3202941
        if (is_string($provider)) {
          $provider_id = $provider;
          $provider = GeocoderProvider::load($provider);
          if (!$provider instanceof GeocoderProviderInterface) {
            throw new \Exception(sprintf("Unable to define a GeocoderProvider from string '%s'", $provider_id));
          }
        }
        $result = $provider->getPlugin()->reverse($latitude, $longitude);
        if (!isset($result) || $result->isEmpty()) {
          throw new \Exception(sprintf('Unable to reverse geocode coordinates %s and %s with the %s provider.', $latitude, $longitude, $provider->id()));
        }
        return $result;
      }
      catch (\Exception $e) {
        $this->getLogger('geocoder')->warning($e->getMessage());
      }
    }
    return NULL;
  }

}
