<?php

namespace Drupal\search_api_solr\Plugin\search_api\parse_mode;

use Drupal\search_api\Plugin\search_api\parse_mode\Terms;

/**
 * Represents a parse mode that parses the sentence into a fuzzy search.
 *
 * @SearchApiParseMode(
 *   id = "fuzzy_terms",
 *   label = @Translation("Multiple words with fuzziness"),
 *   description = @Translation("The query is interpreted as multiple keywords separated by spaces. Fuzzy searches discover terms that are similar to a specified term without necessarily being an exact match. Note: In many cases, stemming (reducing terms to a common stem) can produce similar effects to fuzzy searches. Stemming is enabled for most variations of the fulltext field types."),
 * )
 */
class FuzzyTerms extends Terms {

}
