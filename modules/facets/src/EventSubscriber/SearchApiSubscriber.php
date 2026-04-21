<?php

namespace Drupal\facets\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the SearchApiSubscriber class.
 *
 * @package Drupal\facets\EventSubscriber
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  private $facetManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facet manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetManager = $facetManager;
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();

    if ($query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      // It's safe to hardcode this to the search api scheme because this is in
      // an event subscriber. If this generated source is not correct,
      // implementing the same subscriber and directly calling
      // $manager->alterQuery($query, $your_facetsource_id); will fix that.
      $facet_source = 'search_api:' . str_replace(':', '__', $query->getSearchId());

      // Add the active filters.
      $this->facetManager->alterQuery($query, $facet_source);
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
