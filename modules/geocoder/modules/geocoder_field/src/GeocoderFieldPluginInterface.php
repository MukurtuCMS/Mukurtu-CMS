<?php

namespace Drupal\geocoder_field;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for field plugins.
 */
interface GeocoderFieldPluginInterface {

  /**
   * Provides the third party field settings subform.
   *
   * The returned form API element will be added in behalf of 'geocoder_field'
   * module as third party settings to the field that is storing the geocoding
   * result.
   *
   * @param \Drupal\Core\Field\FieldConfigInterface $field
   *   The field config.
   * @param array $form
   *   The form API form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   A form API form.
   */
  public function getSettingsForm(FieldConfigInterface $field, array $form, FormStateInterface &$form_state);

  /**
   * Validates the field settings form.
   *
   * @param array $form
   *   The form API form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function validateSettingsForm(array $form, FormStateInterface &$form_state);

}
