<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\mukurtu_collection\Plugin\Menu\CollectionMenuItem;

/**
 * Discovers menu links from collection hierarchies.
 */
class CollectionMenuLinkDiscovery implements CollectionMenuLinkDiscoveryInterface {

  /**
   * The collection hierarchy service.
   *
   * @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface
   */
  protected $hierarchyService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CollectionMenuLinkDiscovery.
   *
   * @param \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $hierarchyService
   *   The collection hierarchy service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(CollectionHierarchyServiceInterface $hierarchyService, EntityTypeManagerInterface $entityTypeManager) {
    $this->hierarchyService = $hierarchyService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkDefinitions(?CollectionInterface $collection = NULL) {
    $definitions = [];

    if ($collection) {
      // Get the root collection for this specific collection.
      $rootCollection = $this->hierarchyService->getRootCollectionForCollection((int) $collection->id());
      $rootCollections = $rootCollection ? [$rootCollection] : [];
    }
    else {
      // Get all root collections.
      $rootCollections = $this->hierarchyService->getRootCollections();
    }

    foreach ($rootCollections as $rootCollection) {
      if (!$rootCollection instanceof CollectionInterface) {
        continue;
      }

      // Get the full hierarchy for this root collection.
      $hierarchy = $this->hierarchyService->getCollectionHierarchy((int) $rootCollection->id());

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
   * @param array $hierarchyNode
   *   A hierarchy node with 'entity', 'depth', and 'children' keys.
   * @param array &$definitions
   *   Array to store menu link definitions.
   * @param string|null $parentUuid
   *   UUID of the parent menu item, if any.
   * @param int $weight
   *   Weight for ordering siblings.
   */
  protected function buildMenuLinksFromHierarchy(array $hierarchyNode, array &$definitions, $parentUuid = NULL, $weight = 0) {
    if (empty($hierarchyNode['entity'])) {
      return;
    }

    /** @var \Drupal\mukurtu_collection\Entity\CollectionInterface $collection */
    $collection = $hierarchyNode['entity'];
    $depth = $hierarchyNode['depth'];

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
    if ($parentUuid !== NULL) {
      $definition['parent'] = 'mukurtu_collection.collection_menu:' . $parentUuid;
    }

    $definitions[$uuid] = $definition;

    // Process children.
    if (!empty($hierarchyNode['children'])) {
      $childWeight = 0;
      foreach ($hierarchyNode['children'] as $childNode) {
        $this->buildMenuLinksFromHierarchy($childNode, $definitions, $uuid, $childWeight);
        $childWeight++;
      }
    }
  }

}
