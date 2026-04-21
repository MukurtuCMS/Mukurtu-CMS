<?php

namespace Drupal\geocoder_address\Plugin\Geocoder\Field;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geocoder_field\Plugin\Geocoder\Field\DefaultField;

/**
 * Provides a Geocoder Address field plugin.
 *
 * @GeocoderField(
 *   id = "address_field",
 *   label = @Translation("Address field plugin"),
 *   field_types = {
 *     "address",
 *     "address_country"
 *   }
 * )
 */
class AddressField extends DefaultField {

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(FieldConfigInterface $field, array $form, FormStateInterface &$form_state) {
    $element = parent::getSettingsForm($field, $form, $form_state);

    // On Address Field the dumper should always be 'geometry'.
    $element['dumper'] = [
      '#type' => 'value',
      '#value' => 'geojson',
    ];

    return $element;
  }

}
