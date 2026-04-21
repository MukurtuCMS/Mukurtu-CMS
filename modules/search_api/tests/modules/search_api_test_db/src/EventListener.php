<?php

namespace Drupal\search_api_test_db;

use Drupal\search_api_db\Event\QueryPreExecuteEvent;
use Drupal\search_api_db\Event\SearchApiDbEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to Database Search events for testing purposes.
 *
 * @see \Drupal\Tests\search_api_db\Kernel\BackendTest
 */
class EventListener implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiDbEvents::QUERY_PRE_EXECUTE => 'queryPreExecute',
    ];
  }

  /**
   * Reacts to the query pre-execute event.
   *
   * @param \Drupal\search_api_db\Event\QueryPreExecuteEvent $event
   *   The query pre-execute event.
   */
  public function queryPreExecute(QueryPreExecuteEvent $event) {
    $option = 'search_api_test_db.event.query_pre_execute';
    if ($event->getQuery()->getOption("$option.1")) {
      $event->getDbQuery()->alwaysFalse();
    }
    if ($event->getQuery()->getOption("$option.2")) {
      $db_query = \Drupal::database()->select('search_api_item')->alwaysFalse();
      $event->setDbQuery($db_query);
    }
  }

}
