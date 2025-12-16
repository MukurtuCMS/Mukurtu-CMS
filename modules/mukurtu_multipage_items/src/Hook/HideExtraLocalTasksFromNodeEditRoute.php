<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hide extra local tasks from node edit route.
 */
class HideExtraLocalTasksFromNodeEditRoute {

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    // Remove the "Edit Page" task when on the node edit form route.
    if ($route_name === 'entity.node.edit_form' && isset($data['tabs'][0]['mukurtu_multipage_items.multipage_node_view.edit_page_form'])) {
      unset($data['tabs'][0]['mukurtu_multipage_items.multipage_node_view.edit_page_form']);
    }
    if ($route_name === 'entity.node.edit_form' && isset($data['tabs'][0]['mukurtu_multipage_items.multipage_node_view'])) {
      unset($data['tabs'][0]['mukurtu_multipage_items.multipage_node_view']);
    }
  }

}
