<?php

namespace Drupal\search_api_solr;

/**
 * Provides autocomplete related methods used by search backends and plugins.
 */
trait SolrAutocompleteBackendTrait {

  /**
   * Returns a Solarium autocomplete query.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   Defines an interface for Solr search backend plugins.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_solr\Solarium\Autocomplete\Query|null
   *   The Solarium autocomplete query or NULL if the Solr version is not
   *   compatible.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getAutocompleteQuery(SolrBackendInterface $backend, &$incomplete_key, &$user_input) {
    // Make the input lowercase as the indexed data is (usually) also all
    // lowercase.
    $incomplete_key = mb_strtolower($incomplete_key);
    $user_input = mb_strtolower($user_input);
    $connector = $backend->getSolrConnector();
    $solr_version = $connector->getSolrVersion();
    if (version_compare($solr_version, '6.5', '=')) {
      $this->getLogger()
        ->error('Solr 6.5.x contains a bug that breaks the autocomplete feature. Downgrade to 6.4.x or upgrade to 6.6.x at least.');
      return NULL;
    }

    return $connector->getAutocompleteQuery();
  }

  /**
   * Removes duplicated autocomplete suggestions from the given array.
   *
   * @param array $suggestions
   *   The array of suggestions.
   */
  protected function filterDuplicateAutocompleteSuggestions(array &$suggestions) {
    $added_suggestions = [];
    $added_urls = [];
    /** @var \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface $suggestion */
    foreach ($suggestions as $key => $suggestion) {
      if (
        !in_array($suggestion->getSuggestedKeys(), $added_suggestions, TRUE) ||
        !in_array($suggestion->getUrl(), $added_urls, TRUE)
      ) {
        $added_suggestions[] = $suggestion->getSuggestedKeys();
        $added_urls[] = $suggestion->getUrl();
      }
      else {
        unset($suggestions[$key]);
      }
    }
  }

}
