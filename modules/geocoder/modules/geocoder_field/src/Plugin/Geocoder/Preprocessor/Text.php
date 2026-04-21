<?php

namespace Drupal\geocoder_field\Plugin\Geocoder\Preprocessor;

use Drupal\geocoder_field\PreprocessorBase;

/**
 * Provides a geocoder preprocessor plugin for text fields.
 *
 * @todo [cc]: What is the reason for this plugin? It returns exactly what it
 *   receives.
 *
 * @GeocoderPreprocessor(
 *   id = "text",
 *   name = "Text",
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "text",
 *     "text_long"
 *   }
 * )
 */
class Text extends PreprocessorBase {}
