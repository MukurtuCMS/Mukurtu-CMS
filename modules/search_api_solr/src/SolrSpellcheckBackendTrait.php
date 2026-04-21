<?php

namespace Drupal\search_api_solr;

use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Query\Result\ResultInterface;

/**
 * Provides spellcheck related methods used by search backends and plugins.
 */
trait SolrSpellcheckBackendTrait {

  /**
   * Get the spellcheck suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   An autocomplete query result.
   *
   * @return array
   *   An array of suggestions.
   */
  protected function extractSpellCheckSuggestions(ResultInterface $result) {
    $suggestions = [];
    if ($spellcheck_results = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK)) {
      foreach ($spellcheck_results as $term_result) {
        $keys = [];
        /** @var \Solarium\Component\Result\Spellcheck\Suggestion $term_result */
        foreach ($term_result->getWords() as $correction) {
          $keys[] = $correction['word'];
        }
        if ($keys) {
          $suggestions[$term_result->getOriginalTerm()] = $keys;
        }
      }
    }
    return $suggestions;
  }

}
