<?php

namespace Drupal\geocoder\Plugin\Geocoder\Provider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;
use Geocoder\Provider\Mapbox\Mapbox as MapboxProvider;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;

/**
 * Provides a Mapbox geocoder provider plugin.
 *
 * @GeocoderProvider(
 *   id = "mapbox",
 *   name = "Mapbox",
 *   handler = "\Geocoder\Provider\Mapbox\Mapbox",
 *   arguments = {
 *     "accessToken" = "",
 *     "country" = "",
 *     "geocodingMode" = "mapbox.places",
 *   }
 * )
 */
class Mapbox extends ConfigurableProviderUsingHandlerWithAdapterBase {

  /**
   * {@inheritdoc}
   */
  protected function doGeocode($source) {
    $this->throttle->waitForAvailability($this->pluginId, $this->configuration['throttle'] ?? []);

    if (!$this->getHandler() instanceof Provider) {
      return NULL;
    }

    $query = GeocodeQuery::create($source)
      ->withData('fuzzy_match', $this->configuration['fuzzy_match'])
      ->withData('location_type', explode(',', $this->configuration['location_type']));

    return $this->getHandlerWrapper()->geocodeQuery($query);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'location_type' => MapboxProvider::DEFAULT_TYPE,
      'fuzzy_match' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config_schema_definition = $this->getConfigSchemaDefinition();

    $location_type_definition = $config_schema_definition['mapping']['location_type'];
    $form['options']['location_type'] = [
      '#type' => 'textfield',
      '#title' => $location_type_definition['label'] ?? '',
      '#description' => $location_type_definition['description'] ?? '',
      '#default_value' => $this->configuration['location_type'],
      '#required' => empty($location_type_definition['nullable']),
      '#weight' => 5,
    ];

    $fuzzy_match_definition = $config_schema_definition['mapping']['fuzzy_match'];
    $form['options']['fuzzy_match'] = [
      '#type' => 'checkbox',
      '#title' => $fuzzy_match_definition['label'] ?? '',
      '#description' => $fuzzy_match_definition['description'] ?? '',
      '#default_value' => $this->configuration['fuzzy_match'],
      '#required' => empty($fuzzy_match_definition['nullable']),
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['location_type'] = $form_state->getValue('location_type');
    $this->configuration['fuzzy_match'] = $form_state->getValue('fuzzy_match');
  }

}
