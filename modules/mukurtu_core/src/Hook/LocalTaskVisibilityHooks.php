<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hides debug/history local tasks on entity view pages.
 */
class LocalTaskVisibilityHooks {

  /**
   * Entity types that get the Devel/Revisions view-page treatment.
   */
  const ENTITY_TYPES = ['node', 'community', 'protocol'];

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    // The Visitors tab never needs to show, on view or edit.
    if ($route_name === 'entity.node.canonical' || $route_name === 'entity.node.edit_form') {
      unset($data['tabs'][0]['visitors.node_tab']);
    }

    // Devel and Revisions only need to show on the edit page, not the view
    // page.
    foreach (self::ENTITY_TYPES as $entity_type_id) {
      if ($route_name === "entity.$entity_type_id.canonical") {
        unset($data['tabs'][0]["devel.entities:$entity_type_id.devel_tab"]);
        unset($data['tabs'][0]["entity.$entity_type_id.version_history"]);
        unset($data['tabs'][0]["entity.version_history:$entity_type_id.version_history"]);
      }
    }
  }

}
