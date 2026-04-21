<?php

namespace Drupal\geocoder_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase;

/**
 * Plugin implementation of the Geocode formatter.
 *
 * @FieldFormatter(
 *   id = "geocoder_geocode_formatter",
 *   label = @Translation("Geocode"),
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "text",
 *     "text_long",
 *   }
 * )
 */
class GeocodeFormatter extends GeocodeFormatterBase {

  /**
   * Geocoder Plugins not compatible with this Formatter Filed Types.
   *
   * @var array
   */
  protected $incompatiblePlugins = [
    'file',
    'gpxfile',
    'kmlfile',
    'geojsonfile',

  ];

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Filter out the Geocoder Plugins that are not compatible with the Geocode
    // Formatter action.
    $compatible_providers = array_filter($element['providers'], function ($e) {
      $geocoder_providers = $this->geocoderProviders;
      if (isset($geocoder_providers[$e]) && $geocoder_provider = $geocoder_providers[$e]) {
        /** @var \Drupal\geocoder\Entity\GeocoderProvider $geocoder_provider */
        /** @var \Drupal\Component\Plugin\PluginBase $plugin */
        $plugin = $geocoder_provider->getPlugin();
        return !in_array($plugin->getPluginId(), $this->incompatiblePlugins);
      }
      return TRUE;

    }, ARRAY_FILTER_USE_KEY);

    // Generate a warning markup in case of no compatible Geocoder Provider.
    if (count($element['providers']) - count($compatible_providers) == count($this->geocoderProviders)) {
      $element['warning'] = [
        '#markup' => $this->t('Any compatible Geocoder Provider available for this Formatter.'),
      ];
    }
    $element['providers'] = $compatible_providers;
    return $element;

  }

}
