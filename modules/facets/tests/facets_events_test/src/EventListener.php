<?php

namespace Drupal\facets_events_test;

use Drupal\facets\Event\FacetsEvents;
use Drupal\facets\Event\QueryStringCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the EventListener class.
 */
class EventListener implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FacetsEvents::QUERY_STRING_CREATED => 'queryStringCreated',
    ];
  }

  /**
   * Event handler for the query string created event.
   *
   * @param \Drupal\facets\Event\QueryStringCreated $event
   *   The query string created event.
   */
  public function queryStringCreated(QueryStringCreated $event) {
    $event->getQueryParameters()->add(['test' => 'fun']);
  }

}
