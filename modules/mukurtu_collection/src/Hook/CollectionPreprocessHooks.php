<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;

/**
 * Hook implementations for collection preprocessing.
 */
final class CollectionPreprocessHooks {

  /**
   * Constructs a new CollectionPreprocessHooks.
   *
   * @param \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $hierarchyService
   *   Collection hierarchy service.
   */
  public function __construct(protected CollectionHierarchyServiceInterface $hierarchyService) {
  }

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['node'];

    // Only process collection nodes in the full view mode.
    if ($node->bundle() !== 'collection' || $variables['view_mode'] !== 'full') {
      return;
    }

    // Get the collection entity.
    $collection = $this->hierarchyService->getCollectionFromNode($node);

    if (!$collection) {
      return;
    }

    // Look up the root collection.
    $root_collection = $this->hierarchyService->getRootCollectionForCollection((int) $collection->id());

    if ($root_collection && $root_collection->access('view')) {
      // Provide the root collection as a link.
      $variables['root_collection'] = $root_collection->toLink()->toRenderable();
      $variables['root_collection']['#attributes'] = (new Attribute())->addClass('collection__root-link')->toArray();
      CacheableMetadata::createFromRenderArray($variables['root_collection'])
        ->addCacheableDependency($root_collection)
        ->applyTo($variables['root_collection']);
    }
  }

}
