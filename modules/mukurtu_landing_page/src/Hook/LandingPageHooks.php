<?php

declare(strict_types=1);

namespace Drupal\mukurtu_landing_page\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for the mukurtu_landing_page module.
 */
class LandingPageHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

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
