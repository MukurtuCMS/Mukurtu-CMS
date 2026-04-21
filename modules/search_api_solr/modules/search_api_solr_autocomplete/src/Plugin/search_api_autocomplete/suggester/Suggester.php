<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\Solarium\Autocomplete\Result;
use Drupal\search_api_solr\SolrAutocompleteBackendTrait;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Drupal\search_api_solr_autocomplete\Event\PreSuggesterQueryEvent;
use Solarium\Component\ComponentAwareQueryInterface;

/**
 * Provides a suggester plugin that retrieves suggestions from the server.
 *
 * The server needs to support the "search_api_autocomplete" feature for this to
 * work.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "search_api_solr_suggester",
 *   label = @Translation("Solr Suggester"),
 *   description = @Translation("Suggest complete phrases for the entered string based on Solr's suggest component."),
 * )
 */
class Suggester extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;
  use BackendTrait;
  use SolrAutocompleteBackendTrait;
  use LoggerTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public static function supportsSearch(SearchInterface $search) {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = static::getBackend($search->getIndex());
    return ($backend && version_compare($backend->getSolrConnector()->getSolrMajorVersion(), '6', '>='));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_api_solr/site_hash' => TRUE,
      'search_api/index' => '',
      'drupal/langcode' => 'any',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $search = $this->getSearch();
    $server = $search->getIndex()->getServerInstance();

    $form['search_api_solr/site_hash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('From this site only'),
      '#description' => $this->t('Limit the suggestion dictionary to entries created by this site in case of a multisite setup.'),
      '#default_value' => $this->getConfiguration()['search_api_solr/site_hash'],
    ];

    $index_options['any'] = $this->t('Any index');
    foreach ($server->getIndexes() as $index) {
      $index_options[$index->id()] = $this->t('Index @index', ['@index' => $index->label()]);
    }

    $form['search_api/index'] = [
      '#type' => 'radios',
      '#title' => $this->t('Index'),
      '#description' => $this->t('Limit the suggestion dictionary to entries to those created by a specific index.'),
      '#options' => $index_options,
      '#default_value' => $this->getConfiguration()['search_api/index'] ?: $search->getIndex()->id(),
    ];

    $langcode_options['any'] = $this->t('Any language');
    $langcode_options['multilingual'] = $this->t('Let the Solr server handle it dynamically.');
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $langcode_options[$language->getId()] = $language->getName();
    }
    $langcode_options[LanguageInterface::LANGCODE_NOT_SPECIFIED] = $this->t('Undefined');

    $form['drupal/langcode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Language'),
      '#description' => $this->t('Limit the suggestion dictionary to entries that belong to a specific language.'),
      '#options' => $langcode_options,
      '#default_value' => $this->getConfiguration()['drupal/langcode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->setConfiguration($values);
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

    $config = $this->getConfiguration();
    $options['context_filter_tags'] = [];
    if ($config['search_api_solr/site_hash']) {
      $options['context_filter_tags'][] = 'search_api_solr/site_hash:' . Utility::getSiteHash();
    }
    if (!empty($config['search_api/index']) && 'any' !== $config['search_api/index']) {
      $options['context_filter_tags'][] = 'search_api/index:' . $config['search_api/index'];
    }
    if ('any' !== $config['drupal/langcode']) {
      $options['context_filter_tags'][] = 'drupal/langcode:' . $config['drupal/langcode'];
    }

    return $this->getSuggesterSuggestions($backend, $query, $incomplete_key, $user_input, $options);
  }

  /**
   * Autocompletion suggestions for some user input using Suggester component.
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
   * @param array $options
   *   (optional) An associative array of options with 'dictionary' as string,
   *   'context_filter_tags' as array of strings.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getSuggesterSuggestions(SolrBackendInterface $backend, QueryInterface $query, $incomplete_key, $user_input, array $options = []) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($backend, $incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteSuggesterQuery($query, $solarium_query, $user_input, $options);
        // Allow modules to alter the solarium autocomplete query.
        $event = new PreSuggesterQueryEvent($query, $solarium_query);
        $backend->dispatch($event);
        $result = $backend->getSolrConnector()->autocomplete($solarium_query, $backend->getCollectionEndpoint($query->getIndex()));
        $suggestions = $this->getAutocompleteSuggesterSuggestions($result, $suggestion_factory);
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
   * Set the suggester parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   A Solarium autocomplete query.
   * @param string $user_input
   *   The user input.
   * @param array $options
   *   (optional) An array of options.
   *   e.g: 'dictionary' as string, 'context_filter_tags' as array of strings.
   */
  protected function setAutocompleteSuggesterQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $user_input, array $options = []) {
    $langcodes = Utility::ensureLanguageCondition($query);

    if (isset($options['context_filter_tags'])) {
      if (in_array('drupal/langcode:multilingual', $options['context_filter_tags'])) {
        if ($langcodes && count($langcodes) === 1) {
          $langcode = reset($langcodes);
          $options['context_filter_tags'] = str_replace('drupal/langcode:multilingual', 'drupal/langcode:' . $langcode, $options['context_filter_tags']);
          $options['dictionary'] = $langcode;
        }
        else {
          // Use multiple dictionaries and langcodes.
          $tag_name = Utility::encodeSolrName('drupal/langcode:');
          $options['context_filter_tags'] = str_replace('drupal/langcode:multilingual', '(' . $tag_name . implode(' ' . $tag_name, $langcodes) . ')', $options['context_filter_tags']);
          $options['dictionary'] = $langcodes;
        }
      }
      else {
        foreach ($options['context_filter_tags'] as $key => $tag) {
          if (strpos($tag, 'drupal/langcode:') === 0) {
            $langcode_array = explode(':', $tag);
            if (isset($langcode_array[1]) && 'any' !== $langcode_array[1]) {
              $options['dictionary'] = $langcode_array[1] ?: LanguageInterface::LANGCODE_NOT_SPECIFIED;
              break;
            }
          }
        }
      }

      if (empty($options['dictionary'])) {
        foreach ($options['context_filter_tags'] as $key => $tag) {
          if (strpos($tag, 'drupal/langcode:') === 0) {
            unset($options['context_filter_tags'][$key]);
            break;
          }
        }
      }
    }

    $suggester_component = $solarium_query->getSuggester();
    $suggester_component->setQuery($user_input);
    $suggester_component->setDictionary(!empty($options['dictionary']) ? $options['dictionary'] : LanguageInterface::LANGCODE_NOT_SPECIFIED);
    if (!empty($options['context_filter_tags'])) {
      $suggester_component->setContextFilterQuery(
        Utility::buildSuggesterContextFilterQuery($options['context_filter_tags']));
    }
    $suggester_component->setCount($query->getOption('limit') ?? 10);
    // The search_api_autocomplete module highlights by itself.
    $solarium_query->addParam('suggest.highlight', FALSE);
  }

  /**
   * Get the term suggestions from the autocomplete query result.
   *
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Result $result
   *   An autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteSuggesterSuggestions(Result $result, SuggestionFactory $suggestion_factory) {
    $suggestions = [];
    if ($phrases_result = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_SUGGESTER)) {
      /** @var \Solarium\Component\Result\Suggester\Result $phrases_result */
      $dictionaries = array_keys($phrases_result->getResults());
      foreach ($phrases_result->getAll() as $dictionary_index => $phrases) {
        foreach ($phrases->getSuggestions() as $phrase) {
          $suggestion = $suggestion_factory->createFromSuggestedKeys($phrase['term']);
          if (method_exists($suggestion, 'setDictionary')) {
            $suggestion->setDictionary($dictionaries[$dictionary_index]);
          }
          $suggestions[] = $suggestion;
        }
      }
    }
    return $suggestions;
  }

}
