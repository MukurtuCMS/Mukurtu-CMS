<?php

namespace Drupal\geocoder_geofield\Plugin\Geocoder\Preprocessor;

use Drupal\geocoder_field\PreprocessorBase;

/**
 * Provides a geocoder preprocessor plugin for geofield fields.
 *
 * @GeocoderPreprocessor(
 *   id = "geofield",
 *   name = "Geofield",
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class Geofield extends PreprocessorBase {}
