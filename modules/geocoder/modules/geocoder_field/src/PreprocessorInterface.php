<?php

namespace Drupal\geocoder_field;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides an interface for geocoder preprocessor plugins.
 *
 * Preprocessors are plugins that knows to format source data before it's sent
 * to geocoding.
 */
interface PreprocessorInterface {

  /**
   * Sets the field that needs to be preprocessed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field that needs to be preprocessed.
   *
   * @return $this
   */
  public function setField(FieldItemListInterface $field);

  /**
   * Processes the values of the field before geocoding.
   *
   * @return $this
   */
  public function preprocess();

  /**
   * Get prepared reverse geocode values.
   *
   * @todo [cc]: When fixing reverse operation, clarify the interface for this
   *   method, including the method name.
   */
  public function getPreparedReverseGeocodeValues(array $values = []);

}
