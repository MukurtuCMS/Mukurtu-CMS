<?php

namespace Drupal\search_api_solr_autocomplete\Event;

/**
 * Defines events for the Search API Solr Autocomplete module.
 *
 * You can also leverage any solarium event:
 *
 * @see https://solarium.readthedocs.io/en/stable/customizing-solarium/#plugin-system
 * @see https://github.com/solariumphp/solarium/blob/master/src/Core/Event/Events.php
 */
final class SearchApiSolrAutocompleteEvents {

  /**
   * Let modules alter the Solarium select query before executing it.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr_autocomplete\Event\PreSpellcheckQueryEvent
   */
  const PRE_SPELLCHECK_QUERY = PreSpellcheckQueryEvent::class;

  /**
   * Let modules alter the Solarium select query before executing it.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr_autocomplete\Event\PreSpellcheckQueryEvent
   */
  const PRE_SUGGESTER_QUERY = PreSuggesterQueryEvent::class;

}
