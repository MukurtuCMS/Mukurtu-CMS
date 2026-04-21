<?php

namespace Drupal\search_api_solr\Plugin\search_api\parse_mode;

use Drupal\search_api\Plugin\search_api\parse_mode\Terms;

/**
 * Represents a parse mode that parses the sentence into a sloppy search.
 *
 * @SearchApiParseMode(
 *   id = "sloppy_terms",
 *   label = @Translation("Multiple words with sloppiness"),
 *   description = @Translation("The query is interpreted as multiple keywords separated by spaces. Keywords containing spaces may be ""quoted"" and interpreted as a single phrase. Solr will also show results where the words are not directly positioned next to each other. The scoring will be lower the further away the words are from eachother. Quoted keywords must still be separated by spaces."),
 * )
 */
class SloppyTerms extends Terms {

}
