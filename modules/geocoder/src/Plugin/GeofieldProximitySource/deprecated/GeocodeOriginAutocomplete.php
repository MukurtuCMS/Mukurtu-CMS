<?php

namespace Drupal\geocoder\Plugin\GeofieldProximitySource\deprecated;

use Drupal\geocoder\FormatterPluginManager;
use Drupal\geocoder\Geocoder;
use Drupal\geocoder\Plugin\GeofieldProximitySource\GeocodeOrigin;
use Drupal\geocoder\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'Geocode Origin, with Autocomplete' proximity source plugin.
 *
 * @GeofieldProximitySource(
 *   id = "geofield_geocode_origin_autocomplete",
 *   label = @Translation("Geocode Origin, with Autocomplete"),
 *   description = @Translation("Geocodes origin from free text input, with autocomplete."),
 *   exposedDescription = @Translation("Geocode origin from free text input, with autocomplete."),
 *   exposedOnly = true,
 *   context = {},
 *   no_ui = true
 * )
 */
class GeocodeOriginAutocomplete extends GeocodeOrigin {

  /**
   * Constructs a GeocodeOrigin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geocoder\Geocoder $geocoder
   *   The Geocoder Service.
   * @param \Drupal\geocoder\ProviderPluginManager $providerPluginManager
   *   The Providers Plugin Manager.
   * @param \Drupal\geocoder\FormatterPluginManager $formatterPluginManager
   *   The Providers Plugin Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Geocoder $geocoder, ProviderPluginManager $providerPluginManager, FormatterPluginManager $formatterPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $geocoder, $providerPluginManager, $formatterPluginManager);
    $this->useAutocomplete = $configuration['use_autocomplete'] ?? 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geocoder'),
      $container->get('plugin.manager.geocoder.provider'),
      $container->get('plugin.manager.geocoder.formatter')
    );
  }

}
