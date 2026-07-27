<?php

declare(strict_types=1);

namespace Drupal\mukurtu_landing_page\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the mukurtu_landing_page module.
 */
class LandingPageHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_node_access_records().
   *
   * MukurtuNode::access('view') returns allowed() for all landing pages,
   * making them publicly viewable. Reflect that in the SQL grant table so
   * landing pages appear in Views (e.g. Manage All Content) for non-admins.
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    if ($node->bundle() !== 'landing_page' || !$node->isPublished()) {
      return [];
    }
    return [
      [
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
        'priority' => 0,
      ],
    ];
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, string $route_name): void {
    if ($route_name !== 'entity.node.canonical') {
      return;
    }
    $node = $this->routeMatch->getParameter('node');
    if ($node && $node->bundle() === 'landing_page') {
      $data['tabs'] = [];
    }
  }

}
