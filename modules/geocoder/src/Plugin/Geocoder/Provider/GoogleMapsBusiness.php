<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;
use Geocoder\Provider\GoogleMaps\GoogleMaps;

/**
 * Geocoder provider plugin for Google Maps for Business.
 *
 * @GeocoderProvider(
 *   id = "googlemaps_business",
 *   name = "GoogleMapsBusiness",
 *   handler = "\Geocoder\Provider\GoogleMaps\GoogleMaps",
 *   arguments = {
 *     "clientId" = "",
 *     "privateKey" = "",
 *     "region" = "",
 *     "apiKey" = "",
 *     "channel" = ""
 *   }
 * )
 */
class GoogleMapsBusiness extends ConfigurableProviderUsingHandlerWithAdapterBase {

  /**
   * {@inheritdoc}
   */
  protected function getHandler() {
    if ($this->handler === NULL) {
      $this->handler = GoogleMaps::business(
        $this->httpAdapter,
        $this->configuration['clientId'],
        $this->configuration['privateKey'],
        $this->configuration['region'],
        $this->configuration['apiKey'],
        $this->configuration['channel']
      );
    }
    return $this->handler;
  }

}
