<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\Entity\Collection;

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
    if (!$node instanceof Collection || $variables['view_mode'] !== 'full') {
      return;
    }

    // Look up the root collection.
    $root_collection = $this->hierarchyService->getRootCollectionForCollection($node);

    if ($root_collection && $root_collection->access()) {
      // Provide the root collection as a link.
      $variables['root_collection'] = $root_collection->toLink()->toRenderable();
      $variables['root_collection']['#attributes'] = (new Attribute())->addClass('collection__root-link')->toArray();
      CacheableMetadata::createFromRenderArray($variables['root_collection'])
        ->addCacheableDependency($root_collection)
        ->applyTo($variables['root_collection']);
      // Provide a flag to indicate if the root collection has any children.
      $variables['root_collection_has_children'] = count($root_collection->getChildCollectionIds()) > 0;
    }
  }

}
