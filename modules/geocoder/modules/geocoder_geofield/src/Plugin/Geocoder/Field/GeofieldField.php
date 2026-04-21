<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Field;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geocoder_field\Plugin\Geocoder\Field\DefaultField;

/**
 * Provides a geofield geocoder field plugin.
 *
 * @GeocoderField(
 *   id = "geofield",
 *   label = @Translation("Geofield field plugin"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldField extends DefaultField {

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(FieldConfigInterface $field, array $form, FormStateInterface &$form_state) {
    $element = parent::getSettingsForm($field, $form, $form_state);

    // The Geofield can just be object of Geocoding.
    $element['method']['#options'] = [
      'none' => $this->t('No geocoding'),
      'geocode' => $this->t('<b>Geocode</b> from an existing field'),
    ];

    // On Geofield the dumper should always be 'wkt'.
    $element['dumper'] = [
      '#type' => 'value',
      '#value' => 'wkt',
    ];

    return $element;
  }

}
