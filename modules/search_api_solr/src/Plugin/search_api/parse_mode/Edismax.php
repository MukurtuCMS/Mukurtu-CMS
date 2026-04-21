<?php

namespace Drupal\search_api_solr\Plugin\search_api\parse_mode;

use Drupal\search_api\Plugin\search_api\parse_mode\Terms;

/**
 * Represents a parse mode that parses the input into multiple words.
 *
 * @SearchApiParseMode(
 *   id = "edismax",
 *   label = @Translation("Multiple words with EDisMax"),
 *   description = @Translation("The query is interpreted as multiple keywords separated by spaces. Keywords containing spaces may be ""quoted"". Quoted keywords must still be separated by spaces. Solr will handle the keywords using an EDisMax query parser."),
 * )
 */
class Edismax extends Terms {

}
