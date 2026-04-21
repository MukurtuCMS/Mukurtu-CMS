<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Provides a custom full text data type.
 *
 * @SearchApiDataType(
 *   id = "solr_text_custom",
 *   label = @Translation("Fulltext Custom"),
 *   description = @Translation("Custom full text field."),
 *   fallback_type = "text",
 *   prefix = "tc",
 *   deriver = "Drupal\search_api_solr\Plugin\Derivative\CustomTextDataType"
 * )
 */
class CustomTextDataType extends TextDataType {}
