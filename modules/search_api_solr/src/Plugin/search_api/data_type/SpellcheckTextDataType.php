<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

/**
 * Provides data type to feed the suggester component.
 *
 * @SearchApiDataType(
 *   id = "solr_text_spellcheck",
 *   label = @Translation("Spellcheck"),
 *   description = @Translation("Full text field to feed the spellcheck component."),
 *   fallback_type = "text",
 *   prefix = "spellcheck"
 * )
 */
class SpellcheckTextDataType extends WhiteSpaceTokensTextDataType {}
