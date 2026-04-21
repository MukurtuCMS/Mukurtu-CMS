<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\SolrAutocompleteBackendTrait;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrSpellcheckBackendTrait;
use Drupal\search_api_solr_autocomplete\Event\PreSpellcheckQueryEvent;
use Solarium\Core\Query\Result\ResultInterface;

/**
 * Provides a suggester plugin that retrieves suggestions from the server.
 *
 * The server needs to support the "search_api_autocomplete" feature for this to
 * work.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "search_api_solr_spellcheck",
 *   label = @Translation("Solr Spellcheck"),
 *   description = @Translation("Suggest corrections for the entered words based on Solr's spellcheck component. Note: Be careful when activating this feature if you run multiple indexes in one Solr core! The spellcheck component is not able to distinguish between the different indexes and returns suggestions for the complete core. If you run multiple indexes in one core you might get suggestions that lead to zero results on a specific index!"),
 * )
 */
class Spellcheck extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;
  use BackendTrait;
  use SolrAutocompleteBackendTrait;
  use SolrSpellcheckBackendTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public static function supportsSearch(SearchInterface $search) {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = static::getBackend($search->getIndex());
    return ($backend && version_compare($backend->getSolrConnector()->getSolrMajorVersion(), '4', '>='));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

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

    return $this->getSpellcheckSuggestions($backend, $query, $incomplete_key, $user_input);
  }

  /**
   * Autocompletion suggestions for some user input using Spellcheck component.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   The Solr backend.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getSpellcheckSuggestions(SolrBackendInterface $backend, QueryInterface $query, $incomplete_key, $user_input) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($backend, $incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteSpellCheckQuery($backend, $query, $solarium_query, $user_input);
        // Allow modules to alter the solarium autocomplete query.
        $event = new PreSpellcheckQueryEvent($query, $solarium_query);
        $backend->dispatch($event);
        $result = $backend->getSolrConnector()->autocomplete($solarium_query, $backend->getCollectionEndpoint($query->getIndex()));
        $suggestions = $this->getAutocompleteSpellCheckSuggestions($result, $suggestion_factory);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      }
      catch (SearchApiException $e) {
        $this->logException($e);
      }
    }

    return $suggestions;
  }

  /**
   * Set the spellcheck parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   *   The Solr backend.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   A Solarium autocomplete query.
   * @param string $user_input
   *   The user input.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setAutocompleteSpellCheckQuery(SolrBackendInterface $backend, QueryInterface $query, AutocompleteQuery $solarium_query, $user_input) {
    $backend->setSpellcheck($solarium_query, $query, [
      'keys' => [$user_input],
      'count' => $query->getOption('limit') ?? 1,
    ]);
  }

  /**
   * Get the spellcheck suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   An autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteSpellCheckSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory) {
    $suggestions = [];
    foreach ($this->extractSpellCheckSuggestions($result) as $spellcheck_suggestions) {
      foreach ($spellcheck_suggestions as $keys) {
        $suggestions[] = $suggestion_factory->createFromSuggestedKeys($keys);
      }
    }
    return $suggestions;
  }

}
