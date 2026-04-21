<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationMapFormatterBase;

/**
 * Plugin implementation of the 'geofield' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_gpx_file",
 *   module = "geolocation",
 *   label = @Translation("Geolocation GPX Formatter - Map"),
 *   field_types = {
 *     "geolocation_gpx_file",
 *     "file"
 *   }
 * )
 */
class GeolocationGpxMapFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected $dataProviderId = 'geolocation_gpx';

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    unset($element['set_marker']);
    // @todo re-enable?
    unset($element['title']);
    unset($element['info_text']);
    unset($element['replacement_patterns']);

    return $element;
  }

}
