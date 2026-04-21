<?php

namespace Drupal\search_api_solr\Event;

/**
 * Defines events for the Search API Solr module.
 *
 * You can also leverage any solarium event:
 *
 * @see https://solarium.readthedocs.io/en/stable/customizing-solarium/#plugin-system
 * @see https://github.com/solariumphp/solarium/blob/master/src/Core/Event/Events.php
 */
final class SearchApiSolrEvents {

  /**
   * Alter the newly assembled Solr configuration files.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent
   */
  const POST_CONFIG_FILES_GENERATION = PostConfigFilesGenerationEvent::class;

  /**
   * Alter the zip archive of newly assembled Solr configuration files.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostConfigSetGenerationEvent
   */
  const POST_CONFIG_SET_GENERATION = PostConfigSetGenerationEvent::class;

  /**
   * Alter the paths where to find Solr config-set templates.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostConfigSetTemplateMappingEvent
   * @see \Drupal\search_api_solr_legacy\EventSubscriber\SearchApiSolrSubscriber
   */
  const POST_CONFIG_SET_TEMPLATE_MAPPING = PostConfigSetTemplateMappingEvent::class;

  /**
   * Let modules alter the converted Solarium select query before executing it.
   *
   * This event gets fired after the select query is finally converted into an
   * expression that meets the requirements of the targeted query parser. Using
   * this event you can carefully modify the 'q' parameter, in oposite to the
   * PRE_QUERY event.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostConvertedQueryEvent
   */
  const POST_CONVERT_QUERY = PostConvertedQueryEvent::class;

  /**
   * Alter a single Solr document before they are sent to Solr for indexing.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent
   */
  const POST_CREATE_INDEX_DOCUMENT = PostCreateIndexDocumentEvent::class;

  /**
   * Alter Solr documents before they are sent to Solr for indexing.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostCreateIndexDocumentsEvent
   */
  const POST_CREATE_INDEX_DOCUMENTS = PostCreateIndexDocumentsEvent::class;

  /**
   * Let modules alter the facets extracted from Solr's response.
   *
   * Use Search API and Facet API to work with this data.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostExtractFacetsEvent
   */
  const POST_EXTRACT_FACETS = PostExtractFacetsEvent::class;

  /**
   * Let modules alter the search results returned from a Solr search.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostExtractResultsEvent
   */
  const POST_EXTRACT_RESULTS = PostExtractResultsEvent::class;

  /**
   * Change the way the index's field names are mapped to Solr field names.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostFieldMappingEvent
   */
  const POST_FIELD_MAPPING = PostFieldMappingEvent::class;

  /**
   * Fired after all finalization commands to a Solr index have been applied.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostIndexFinalizationEvent
   */
  const POST_INDEX_FINALIZATION = PostIndexFinalizationEvent::class;

  /**
   * Fired after all facets have been set on the Solarium query object.
   *
   * Useful to modify the facets using Solarium's API.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PostSetFacetsEvent
   */
  const POST_SET_FACETS = PostSetFacetsEvent::class;

  /**
   * Fired before a value gets indexed as language fallback.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreAddLanguageFallbackFieldEvent
   */
  const PRE_ADD_LANGUAGE_FALLBACK_FIELD = PreAddLanguageFallbackFieldEvent::class;

  /**
   * Fired before any Search API fields gets mapped to a Solr document.
   *
   * You get access to the empty document object to be indexed later. You might
   * exchange the document with an extended implementation or add low-level
   * fields.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreCreateIndexDocumentEvent
   */
  const PRE_CREATE_INDEX_DOCUMENT = PreCreateIndexDocumentEvent::class;

  /**
   * Fired before facets gets extracted from the Solr response.
   *
   * Useful to modify the facets in the raw result if required.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreExtractFacetsEvent
   */
  const PRE_EXTRACT_FACETS = PreExtractFacetsEvent::class;

  /**
   * Apply any finalization commands to a Solr index before the first search.
   *
   * This event will be fired every time any item within the index was updated
   * or deleted. Not on every modification but before the first search happens
   * on an updated index. This could be useful to apply late modifications to
   * the items themselves within Solr which is much faster.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreIndexFinalizationEvent
   */
  const PRE_INDEX_FINALIZATION = PreIndexFinalizationEvent::class;

  /**
   * Let modules alter the Solarium select query before executing it.
   *
   * After this event, the select query will be finally converted into an
   * expression that will be processed by the lucene query parser. Therefore you
   * can't modify the 'q' parameter here, because it gets overwritten by that
   * conversion. If you need to modify the 'q' parameter you should implement an
   * event subscriber instead of this hook that handles the solarium events (our
   * connector injects the drupal event handler into solarium) or subscribe to
   * POST_CONVERT_QUERY instead. If you want to force a different parser like
   * edismax you must set the 'defType' parameter accordingly.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreQueryEvent
   */
  const PRE_QUERY = PreQueryEvent::class;

  /**
   * Fired before facets are set on the Solarium query object.
   *
   * Using this event you can apply late modifications to the facets set on
   * the Search API query object.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreSetFacetsEvent
   */
  const PRE_SET_FACETS = PreSetFacetsEvent::class;

  /**
   * Fired before the terms based autocomplete solarium query is executed.
   *
   * @Event
   *
   * @see \Drupal\search_api_solr\Event\PreAutocompleteTermsQueryEvent
   */
  const PRE_AUTOCOMPLETE_TERMS_QUERY = PreAutocompleteTermsQueryEvent::class;

}
