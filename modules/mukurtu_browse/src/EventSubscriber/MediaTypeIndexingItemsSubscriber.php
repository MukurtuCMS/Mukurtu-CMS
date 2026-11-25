<?php

namespace Drupal\mukurtu_browse\EventSubscriber;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Item\ItemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjusts media asset bundles before content is indexed for browse.
 */
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
   * Hosts whose embed codes are indexed as 3D models, not external embeds.
   *
   * Matched against the embed code, so the 3D Model facet option finds
   * models embedded from these sites as well as uploaded ones.
   */
  const THREE_D_EMBED_HOSTS = [
    'sketchfab.com',
  ];

  /**
   * Responds to indexing items event.
   *
   * Merges remote media bundles into their local equivalents, counts embeds
   * from 3D model hosts as 3D models, then converts the Solr media type
   * facet's bundles to bundle labels.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   *   The indexing items event.
   */
  public function onIndexingItems(IndexingItemsEvent $event) {
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info_service */
    $bundle_info_service = \Drupal::service('entity_type.bundle.info');
    $media_bundle_info = $bundle_info_service->getBundleInfo('media') ?? [];

    $items = $event->getItems();
    foreach ($items as $item) {
      foreach ($item->getFields() as $field) {
        if ($field->getDatasourceId() === 'entity:node' && $field->getPropertyPath() === 'field_media_assets:entity:bundle') {
          $values = [];
          foreach ($field->getValues() as $value) {
            $values[] = self::MERGED_MEDIA_BUNDLES[$value] ?? $value;
          }
          if (in_array('external_embed', $values, TRUE)) {
            $values = array_merge(array_diff($values, ['external_embed']), $this->embedBundles($item));
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
   * Returns the bundles to index for an item's external embed media.
   *
   * An embed from a 3D model host counts as 3D Model; any other embed as
   * External Embed. Each is returned once.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The indexed node item.
   *
   * @return string[]
   *   'external_embed', '3d_model', or both.
   */
  protected function embedBundles(ItemInterface $item): array {
    try {
      $node = $item->getOriginalObject()->getValue();
    }
    catch (\Exception) {
      return ['external_embed'];
    }
    if (!$node instanceof FieldableEntityInterface || !$node->hasField('field_media_assets')) {
      return ['external_embed'];
    }
    $bundles = [];
    foreach ($node->get('field_media_assets')->referencedEntities() as $media) {
      if ($media->bundle() !== 'external_embed' || !$media->hasField('field_media_external_embed')) {
        continue;
      }
      $code = (string) $media->get('field_media_external_embed')->value;
      $is_3d = FALSE;
      foreach (self::THREE_D_EMBED_HOSTS as $host) {
        $is_3d = $is_3d || str_contains($code, $host);
      }
      $bundles[$is_3d ? '3d_model' : 'external_embed'] = TRUE;
    }
    // The indexed bundle came from somewhere; keep it if no media matched.
    return $bundles ? array_keys($bundles) : ['external_embed'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SearchApiEvents::INDEXING_ITEMS][] = ['onIndexingItems', 0];
    return $events;
  }

}
