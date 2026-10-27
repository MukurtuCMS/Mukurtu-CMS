<?php

namespace Drupal\mukurtu_browse\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Event\IndexingItemsEvent;

class MediaTypeIndexingItemsSubscriber implements EventSubscriberInterface {

  /**
   * Remote media bundles indexed as the local bundle of the same kind.
   *
   * This lets the media type facet show one Audio and one Video option
   * regardless of where the file is hosted.
   */
  const MERGED_MEDIA_BUNDLES = [
    'soundcloud' => 'audio',
    'remote_video' => 'video',
  ];

  /**
   * Responds to indexing items event.
   *
   * Merges remote media bundles into their local equivalents, then converts
   * the Solr media type facet's bundles to bundle labels.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   *   The indexing items event.
   */
  public function onIndexingItems(IndexingItemsEvent $event) {
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service */
    $bundle_info_service = \Drupal::service('entity_type.bundle.info');
    $media_bundle_info = $bundle_info_service->getBundleInfo('media') ?? [];

    $items = $event->getItems();
    foreach ($items as $item_id => $item) {
      foreach ($item->getFields() as $field) {
        if ($field->getDatasourceId() === 'entity:node' && $field->getPropertyPath() === 'field_media_assets:entity:bundle') {
          $values = [];
          foreach ($field->getValues() as $value) {
            $values[] = self::MERGED_MEDIA_BUNDLES[$value] ?? $value;
          }
          $field->setValues(array_values(array_unique($values)));
        }
      }

      if ($field = $item->getField('node__field_media_assets__bundle')) {
        $values = $field->getValues();
        foreach ($values as &$value) {
          $value = $media_bundle_info[$value]['label'] ?? $value;
        }
        $field->setValues($values);
      }
    }

    $event->setItems($items);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SearchApiEvents::INDEXING_ITEMS][] = ['onIndexingItems', 0];
    return $events;
  }

}
