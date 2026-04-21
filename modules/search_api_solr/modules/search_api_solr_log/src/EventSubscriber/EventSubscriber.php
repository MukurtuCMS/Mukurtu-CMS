<?php

namespace Drupal\search_api_solr_log\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api_solr\Utility\Utility;
use Drupal\search_api_solr_log\Logger\SolrLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Commit log events.
 */
class EventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Workaround to avoid a fatal error during site install in some cases.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      return [];
    }

    $events[KernelEvents::TERMINATE][] = ['onTerminate', 100];
    $events[SearchApiEvents::QUERY_PRE_EXECUTE][] = ['onQueryPreExecute'];

    return $events;
  }

  /**
   * Dump delayed log messages.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate request event.
   */
  public function onTerminate(TerminateEvent $event) : void {
    $config = \Drupal::config('search_api_solr_log.settings');
    if ('request' === (string) ($config->get('commit') ?? 'auto')) {
      SolrLogger::commit();
    }
  }

  /**
   * Modify the Search API Query.
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    if ($query->hasTag('views_search_api_solr_log')) {
      $config = \Drupal::config('search_api_solr_log.settings');
      if ($config->get('site_hash') ?? TRUE) {
        $query->addCondition('site_hash', Utility::getSiteHash());
        \Drupal::messenger()->addStatus($this->t('Report is limited to log events of this site.'));
      }
    }
  }

}
