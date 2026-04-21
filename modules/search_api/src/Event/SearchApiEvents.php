<?php

namespace Drupal\search_api\Event;

/**
 * Defines events for the Search API module.
 */
final class SearchApiEvents {

  /**
   * The name of the event fired when determining a server backend's features.
   *
   * This allows modules to change the features that the given server/backend
   * advertises as being supported. This can, for example, be used to disable
   * certain features, or to officially add support for features which are
   * implemented in other contrib modules by altering searches directly.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\DeterminingServerFeaturesEvent
   */
  public const DETERMINING_SERVER_FEATURES = 'search_api.determining_server_features';

  /**
   * The name of the event fired when gathering backend plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_BACKENDS = 'search_api.gathering_backends';

  /**
   * The name of the event fired when gathering datasource plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_DATA_SOURCES = 'search_api.gathering_data_sources';

  /**
   * The name of the event fired when gathering data type plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_DATA_TYPES = 'search_api.gathering_data_types';

  /**
   * The name of the event fired when gathering display plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_DISPLAYS = 'search_api.gathering_displays';

  /**
   * The name of the event fired when gathering parse mode plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_PARSE_MODES = 'search_api.gathering_parse_modes';

  /**
   * The name of the event fired when gathering processor plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_PROCESSORS = 'search_api.gathering_processors';

  /**
   * The name of the event fired when gathering tracker plugins.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\GatheringPluginInfoEvent
   */
  public const GATHERING_TRACKERS = 'search_api.gathering_trackers';

  /**
   * The name of the event fired when preparing items for indexing.
   *
   * This can be used to modify the items in some way before their fields are
   * extracted and they are passed to the server.
   *
   * Be aware that generally preventing the indexing of certain items is
   * deprecated. This is better done with processors, which can easily be
   * configured and only added to indexes where this behavior is wanted. If your
   * module will use this event to reject certain items from indexing, you
   * should document this clearly to avoid confusion.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\IndexingItemsEvent
   */
  public const INDEXING_ITEMS = 'search_api.indexing_items';

  /**
   * The name of the event fired when items have been successfully indexed.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\ItemsIndexedEvent
   */
  public const ITEMS_INDEXED = 'search_api.items_indexed';

  /**
   * The name of the event fired when mapping data types.
   *
   * The mapping is done between types defined by Drupal Core's Typed Data API
   * and the Search API-internal data types. This determines the default type
   * for newly added fields as well as what properties can even be indexed.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\MappingFieldTypesEvent
   */
  public const MAPPING_FIELD_TYPES = 'search_api.mapping_field_types';

  /**
   * The name of the event fired when mapping foreign relationships of an index.
   *
   * Foreign relationships of an index help Search API to mark for reindexing
   * search items affected by changes to entities that are indirectly indexed.
   *
   * This event can be leveraged to alter the map of foreign
   * relationships discovered for any particular search index.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\MappingForeignRelationshipsEvent
   */
  public const MAPPING_FOREIGN_RELATIONSHIPS = 'search_api.mapping_foreign_relationships';

  /**
   * The name of the event fired when building a map of Views field handlers.
   *
   * This is used in the Search API Views integration to create Search
   * API-specific field handlers for all properties of datasources and some
   * entity types.
   *
   * In addition to the definition returned here, for Field API fields, the
   * "field_name" will be set to the field's machine name.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\MappingViewsFieldHandlersEvent
   * @see _search_api_views_get_field_handler_mapping()
   */
  public const MAPPING_VIEWS_FIELD_HANDLERS = 'search_api.mapping_views_field_handlers';

  /**
   * The name of the event fired when building a map of Views handlers.
   *
   * This is used in the Search API Views integration to determine the filter,
   * argument and sort handlers that will be used for fields of that type.
   *
   * Field handlers are not determined by these simplified (Search API) types,
   * but by their actual property data types. For altering that mapping, see
   * \Drupal\search_api\Event\SearchApiEvents::MAPPING_VIEWS_FIELD_HANDLERS.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\MappingViewsFieldHandlersEvent
   * @see _search_api_views_handler_mapping()
   */
  public const MAPPING_VIEWS_HANDLERS = 'search_api.mapping_views_handlers';

  /**
   * The name of the event fired after a search has been executed on the server.
   *
   * This can be used to modify search results or otherwise react to the search.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\ProcessingResultsEvent
   */
  public const PROCESSING_RESULTS = 'search_api.processing_results';

  /**
   * The name of the event fired before executing a search query.
   *
   * This can be used to add additional filters, options or other data to the
   * search query.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\QueryPreExecuteEvent
   */
  public const QUERY_PRE_EXECUTE = 'search_api.query_pre_execute';

  /**
   * The name of the event fired when scheduling an index for re-indexing.
   *
   * When clearing an index or completely rebuilding an index's tracker
   * information, the same hook is fired (as those operations also involve
   * reindexing the complete index contents).
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\ReindexScheduledEvent
   */
  public const REINDEX_SCHEDULED = 'search_api.reindex_scheduled';

  /**
   * Event fired when detecting if a display is rendered in the current request.
   *
   * Detection for whether a specific search display is rendered in the current
   * request is unreliable in some cases, for instance, for Views blocks when
   * placed via the Layout Builder module. For this reason, this event is fired
   * in some situations where a reliable result could not be determined. Event
   * listeners can then modify the result of the detection process.
   *
   * It is important to note that this event will not be fired every time the
   * framework is trying to detect whether a given search display will be
   * rendered, but only under specific circumstances. It is therefore important
   * to check whether the event is even being fired for the scenario in which it
   * is desired to change the result.
   *
   * @Event
   *
   * @see \Drupal\search_api\Event\IsRenderedInCurrentRequestEvent
   */
  public const IS_RENDERED_IN_CURRENT_REQUEST = 'search_api.is_rendered_in_current_request';

}
