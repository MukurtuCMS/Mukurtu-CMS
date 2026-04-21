<?php

namespace Drupal\layout_builder_restrictions_by_region\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Layout Builder Restrictions By Region routes.
 *
 * Adapted from: Drupal\field_ui\Routing\RouteSubscriber.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route_name = $entity_type->get('field_ui_base_route')) {
        // Try to get the route from the current collection.
        if (!$collection->get($route_name)) {
          continue;
        }

        $route = new Route(
          "/admin/layout-builder-restrictions/layout-builder-restrictions-by-region/{$entity_type_id}/allowed-blocks-form",
          [
            '_form' => '\Drupal\layout_builder_restrictions_by_region\Form\AllowedBlocksForm',
            '_title' => 'Allowed Blocks',
          ],
          ['_permission' => 'administer ' . $entity_type_id . ' display']
        );
        $collection->add("layout_builder_restrictions_by_region.{$entity_type_id}_allowed_blocks", $route);
      }
    }

  }

}
