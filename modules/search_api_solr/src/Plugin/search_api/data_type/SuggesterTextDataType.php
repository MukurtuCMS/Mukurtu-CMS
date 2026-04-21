<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

/**
 * Provides data type to feed the suggester component.
 *
 * @SearchApiDataType(
 *   id = "solr_text_suggester",
 *   label = @Translation("Suggester"),
 *   description = @Translation("Full text field to feed the suggester component."),
 *   fallback_type = "text",
 *   prefix = "tw"
 * )
 */
class SuggesterTextDataType extends WhiteSpaceTokensTextDataType {}
