<?php

namespace Drupal\facets_summary\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the SearchApiSubscriber class.
 *
 * @package Drupal\facets_summary\EventSubscriber
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();

    $facet_source_id = 'search_api:' . str_replace(':', '__', $query->getSearchId());
    $storage = $this->entityTypeManager->getStorage('facets_summary');
    // Get all the facet summaries for the facet source.
    $facet_summaries = $storage->loadByProperties(['facet_source_id' => $facet_source_id]);
    /** @var \Drupal\facets_summary\FacetsSummaryInterface $facet_summary */
    foreach ($facet_summaries as $facet_summary) {
      $processors = $facet_summary->getProcessors();
      // If the count processor is enabled, results count must not be skipped.
      if (in_array('show_count', array_keys($processors))) {
        $query->setOption('skip result count', FALSE);
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Workaround to avoid a fatal error during site install from existing
    // config.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      return [];
    }

    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
    ];
  }

}
