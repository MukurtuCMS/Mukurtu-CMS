<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Plugin\Menu\CollectionMenuItem;

/**
 * Discovers menu links from collection hierarchies.
 */
class CollectionMenuLinkDiscovery implements CollectionMenuLinkDiscoveryInterface {

  /**
   * Constructs a new CollectionMenuLinkDiscovery.
   *
   * @param \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $hierarchyService
   *   The collection hierarchy service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected CollectionHierarchyServiceInterface $hierarchyService, protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkDefinitions(?Collection $collection = NULL): array {
    $definitions = [];

    if ($collection) {
      // Get the root collection for this specific collection.
      $root_collection = $this->hierarchyService->getRootCollectionForCollection($collection);
      $root_collections = $root_collection ? [$root_collection] : [];
    }
    else {
      // Get all root collections.
      $root_collections = $this->hierarchyService->getRootCollections();
    }

    foreach ($root_collections as $root_collection) {
      if (!$root_collection instanceof Collection) {
        continue;
      }

      // Get the full hierarchy for this root collection.
      $hierarchy = $this->hierarchyService->getCollectionHierarchy((int) $root_collection->id());

      if (!empty($hierarchy)) {
        // Build menu link definitions from the hierarchy.
        $this->buildMenuLinksFromHierarchy($hierarchy, $definitions);
      }
    }

    return $definitions;
  }

  /**
   * Build menu link definitions from a hierarchy structure.
   *
   * @param array $hierarchy_node
   *   A hierarchy node with 'entity', 'depth', and 'children' keys.
   * @param array &$definitions
   *   Array to store menu link definitions.
   * @param string|null $parent_uuid
   *   UUID of the parent menu item, if any.
   * @param int $weight
   *   Weight for ordering siblings.
   */
  protected function buildMenuLinksFromHierarchy(array $hierarchy_node, array &$definitions, ?string $parent_uuid = NULL, int $weight = 0): void {
    if (empty($hierarchy_node['entity'])) {
      return;
    }

    /** @var \Drupal\mukurtu_collection\Entity\Collection $collection */
    $collection = $hierarchy_node['entity'];
    $depth = $hierarchy_node['depth'];

    // Skip if depth exceeds menu system limits.
    if ($depth >= MenuTreeStorage::MAX_DEPTH) {
      return;
    }

    $uuid = $collection->uuid();
    $url = $collection->toUrl();

    // Build the menu link definition.
    $definition = [
      'class' => CollectionMenuItem::class,
      'menu_name' => 'mukurtu-collection-menu',
      'route_name' => $url->getRouteName(),
      'route_parameters' => $url->getRouteParameters(),
      'options' => $url->getOptions(),
      'title' => $collection->label(),
      'description' => '',
      'weight' => $weight,
      'id' => 'mukurtu_collection.collection_menu:' . $uuid,
      'metadata' => [
        'entity_id' => $collection->id(),
        'depth' => $depth,
      ],
      'enabled' => 1,
      'expanded' => 1,
      'provider' => 'mukurtu_collection',
      'discovered' => 1,
    ];

    // Set parent if this is not a root-level item.
    if ($parent_uuid !== NULL) {
      $definition['parent'] = 'mukurtu_collection.collection_menu:' . $parent_uuid;
    }

    $definitions[$uuid] = $definition;

    // Process children.
    if (!empty($hierarchy_node['children'])) {
      $child_weight = 0;
      foreach ($hierarchy_node['children'] as $child_node) {
        $this->buildMenuLinksFromHierarchy($child_node, $definitions, $uuid, $child_weight);
        $child_weight++;
      }
    }
  }

}
