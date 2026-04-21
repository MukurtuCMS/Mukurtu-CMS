<?php

namespace Drupal\search_api_solr\Controller;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides different listings of SolrFieldType.
 */
trait EventDispatcherTrait {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Returns the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher(): EventDispatcherInterface {
    if (!$this->eventDispatcher) {
      $this->eventDispatcher = \Drupal::getContainer()->get('event_dispatcher');
    }
    return $this->eventDispatcher;
  }

}
