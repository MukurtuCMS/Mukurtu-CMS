<?php

namespace Drupal\mukurtu_browse\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Event\IndexingItemsEvent;

class MediaTypeIndexingItemsSubscriber implements EventSubscriberInterface {

  /**
   * Responds to indexing items event. Convert media asset bundles to bundle labels.
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
