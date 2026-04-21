<?php

namespace Drupal\search_api_solr\EventSubscriber;

use Drupal\search_api\Event\MappingFieldTypesEvent;
use Drupal\search_api\Event\MappingViewsFieldHandlersEvent;
use Drupal\search_api\Event\MappingViewsHandlersEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API events subscriber.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Adds the mapping how to treat some Solr special fields in views.
   *
   * @param \Drupal\search_api\Event\MappingViewsFieldHandlersEvent $event
   *   The Search API event.
   */
  public function onMappingFieldTypes(MappingFieldTypesEvent $event) {
    $mapping = & $event->getFieldTypeMapping();

    $mapping['solr_date'] = 'date';
  }

  /**
   * Adds the mapping how to treat some Solr special fields in views.
   *
   * @param \Drupal\search_api\Event\MappingViewsFieldHandlersEvent $event
   *   The Search API event.
   */
  public function onMappingViewsFieldHandlers(MappingViewsFieldHandlersEvent $event) {
    $mapping = & $event->getFieldHandlerMapping();

    $mapping['solr_date'] = $mapping['datetime_iso8601'];
  }

  /**
   * Adds the mapping how to treat some Solr special fields in views.
   *
   * @param \Drupal\search_api\Event\MappingViewsHandlersEvent $event
   *   The Search API event.
   */
  public function onMappingViewsHandlers(MappingViewsHandlersEvent $event) {
    $mapping = & $event->getHandlerMapping();

    $mapping['solr_text_omit_norms'] =
    $mapping['solr_text_suggester'] =
    $mapping['solr_text_spellcheck'] =
    $mapping['solr_text_unstemmed'] =
    $mapping['solr_text_wstoken'] =
    $mapping['solr_text_custom'] =
    $mapping['solr_text_custom_omit_norms'] = $mapping['text'];

    $mapping['solr_string_storage'] = $mapping['string'];
    $mapping['solr_string_docvalues'] = $mapping['string'];

    // Views can't handle a 'solr_date_range' natively.
    $mapping['solr_date_range'] = $mapping['string'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Workaround to avoid a fatal error during site install in some cases.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      return [];
    }

    return [
      SearchApiEvents::MAPPING_FIELD_TYPES => 'onMappingFieldTypes',
      SearchApiEvents::MAPPING_VIEWS_FIELD_HANDLERS => 'onMappingViewsFieldHandlers',
      SearchApiEvents::MAPPING_VIEWS_HANDLERS =>  'onMappingViewsHandlers',
    ];
  }
}
