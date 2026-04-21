<?php

namespace Drupal\search_api_solr_test\EventSubscriber;

use Drupal\search_api_solr\Event\PostConfigFilesGenerationEvent;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentsEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API Solr events subscriber.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SearchApiSolrEvents::POST_CONFIG_FILES_GENERATION][] = ['postConfigFilesGeneration'];
    $events[SearchApiSolrEvents::POST_CREATE_INDEX_DOCUMENTS][] = ['postCreateIndexDocuments'];

    return $events;
  }

  public function postConfigFilesGeneration(PostConfigFilesGenerationEvent $event): void {
    $files = $event->getConfigFiles();

    $files['test.txt'] =
      "hook_search_api_solr_config_files_alter() works\n" .
      $event->getServerId() . "\n";

    $event->setConfigFiles($files);
  }

  public function postCreateIndexDocuments(PostCreateIndexDocumentsEvent $event): void {
    global $_search_api_solr_test_index_fallback_test;

    if ($_search_api_solr_test_index_fallback_test) {
      $documents = $event->getSolariumDocuments();
      foreach ($documents as $document) {
        if ('entity:entity_test_mulrev_changed/2:en' === $document->ss_search_api_id) {
          // Mess up this document by sending a string as value of a float field.
          $document->setField('fts_width', 'bar');
        }
      }
      $event->setSolariumDocuments($documents);
    }
  }

}
