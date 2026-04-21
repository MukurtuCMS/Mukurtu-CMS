<?php

namespace Drupal\search_api_solr\Plugin\search_api\parse_mode;

use Drupal\search_api\Plugin\search_api\parse_mode\Phrase;

/**
 * Represents a parse mode.
 *
 * A parse mode that parses the sentence into a sloppy search for the sentence.
 *
 * @SearchApiParseMode(
 *   id = "sloppy_phrase",
 *   label = @Translation("Phrase search with sloppiness"),
 *   description = @Translation("The query is interpreted as a single phrase. Solr will also show results where the words are not directly positioned next to each other. The scoring will be lower the further away the words are from eachother"),
 * )
 */
class SloppyPhrase extends Phrase {

}
