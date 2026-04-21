<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\Server;

/**
 * Provides a suggester that retrieves suggestions from Solr's Terms component.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "search_api_solr_terms",
 *   label = @Translation("Solr Terms"),
 *   description = @Translation("Autocomplete the entered string based on Solr's Terms component. Note: Be careful when activating this feature if you run multiple indexes in one Solr core! The Terms component is not able to distinguish between the different indexes and returns matching terms for the complete core. If you run multiple indexes in one core the term counts are not correct and you might get suggestions that lead to zero results on a specific index! You can mitigate that effect if you ensure that the fulltext field names are completely different in the indexes.")
 * )
 */
class Terms extends Server {

  use BackendTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    $backend = static::getBackend($this->getSearch()->getIndex());

    if (!$backend) {
      return [];
    }

    if ($this->configuration['fields']) {
      $query->setFulltextFields($this->configuration['fields']);
    }
    else {
      $query->setFulltextFields($query->getIndex()->getFulltextFields());
    }

    return $backend->getAutocompleteSuggestions($query, $this->getSearch(), $incomplete_key, $user_input);
  }

}
