<?php

namespace Drupal\facets\Event;

/**
 * Defines events for the Facets module.
 */
final class FacetsEvents {

  /**
   * This event allows modules to change the facet's query string if needed.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\QueryStringCreated
   */
  public const QUERY_STRING_CREATED = QueryStringCreated::class;

  /**
   * This event allows modules to change the active filters after parsing them.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\ActiveFiltersParsed
   */
  public const ACTIVE_FILTERS_PARSED = ActiveFiltersParsed::class;

  /**
   * This event allows modules to change the facet links' URL if needed.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\UrlCreated
   */
  public const URL_CREATED = UrlCreated::class;

  /**
   * This event allows modules to modify a facet after it is built.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\PostBuildFacet
   */
  public const POST_BUILD_FACET = PostBuildFacet::class;

  /**
   * This event allows modules to change the cache contexts of a facet.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\GetFacetCacheContexts
   */
  public const GET_FACET_CACHE_CONTEXTS = GetFacetCacheContexts::class;

  /**
   * This event allows modules to change the cache max age of a facet.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\GetFacetCacheMaxAge
   */
  public const GET_FACET_CACHE_MAX_AGE = GetFacetCacheMaxAge::class;

  /**
   * This event allows modules to change the cache tags of a facet.
   *
   * @Event
   *
   * @see \Drupal\facets\Event\GetFacetCacheTags
   */
  public const GET_FACET_CACHE_TAGS = GetFacetCacheTags::class;

}
