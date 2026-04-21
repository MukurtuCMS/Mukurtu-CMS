<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Provides a not stemmed full text data type which omits norms.
 *
 * @SearchApiDataType(
 *   id = "solr_text_custom_omit_norms",
 *   label = @Translation("Fulltext Custom Omit norms"),
 *   description = @Translation("Custom full text field which omits norms."),
 *   fallback_type = "text",
 *   prefix = "toc",
 *   deriver = "Drupal\search_api_solr\Plugin\Derivative\OmitNormsCustomTextDataType"
 * )
 */
class OmitNormsCustomTextDataType extends TextDataType {}
