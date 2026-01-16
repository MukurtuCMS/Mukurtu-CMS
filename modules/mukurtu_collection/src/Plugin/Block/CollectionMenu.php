<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Plugin\Block;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\NodeInterface;
use Drupal\system\Plugin\Block\SystemMenuBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a collection menu block.
 *
 * @Block(
 *   id = "mukurtu_collection_menu",
 *   admin_label = @Translation("Mukurtu Collection Menu"),
 *   category = @Translation("Mukurtu"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Current node"))
 *   }
 * )
 */
class CollectionMenu extends SystemMenuBlock implements ContainerFactoryPluginInterface {

  /**
   * The collection hierarchy service.
   *
   * @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface
   */
  protected CollectionHierarchyServiceInterface $hierarchyService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->hierarchyService = $container->get('mukurtu_collection.hierarchy_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cache = new CacheableMetadata();

    // Get the node from context.
    $node = $this->getContextValue('node');

    if (!$node instanceof NodeInterface) {
      $build = [];
      $cache->applyTo($build);
      return $build;
    }

    if (!$node instanceof Collection) {
      $build = [];
      $cache->addCacheableDependency($node);
      $cache->applyTo($build);
      return $build;
    }

    // Get the root collection for this collection.
    $root_collection = $this->hierarchyService->getRootCollectionForCollection($node);

    if (!$root_collection) {
      $build = [];
      $cache->addCacheableDependency($node);
      $cache->applyTo($build);
      return $build;
    }

    // Add cacheable dependencies.
    $cache->addCacheableDependency($node);
    $cache->addCacheableDependency($root_collection);

    $menu_name = $this->getDerivativeId();

    // Get menu tree parameters.
    if ($this->configuration['expand_all_items']) {
      $parameters = new MenuTreeParameters();
      $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
      $parameters->setActiveTrail($active_trail);
    }
    else {
      $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
    }

    // Set the root to the root collection.
    $parameters->setRoot('mukurtu_collection.collection_menu:' . $root_collection->uuid());

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $parameters->setMinDepth($level);

    // When the depth is configured to zero, there is no depth limit.
    // When depth is non-zero, it indicates the number of levels that must
    // be displayed. Hence this is a relative depth that we must convert to
    // an actual (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    // For menu blocks with start level greater than 1, only show menu items
    // from the current active trail.
    if ($level > 1) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new
        // menu root based on the parent of the configured start level.
        if ($depth > 0) {
          $parameters->setMaxDepth(min($level - 1 + $depth - 1, $this->menuTree->maxDepth()));
        }
      }
      else {
        $build = [];
        $cache->applyTo($build);
        return $build;
      }
    }

    // Load and build the menu tree.
    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    $cache->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    return 'mukurtu-collection-menu';
  }

}
