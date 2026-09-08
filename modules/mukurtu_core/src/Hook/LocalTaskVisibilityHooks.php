<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hides debug/history local tasks on node view pages.
 *
 * Community/protocol get the equivalent Devel/Revisions treatment from
 * mukurtu_protocol's HideCommunityProtocolLocalTasksOutsideEditView, which
 * also preserves the uid-1-only restriction on their Revisions tab.
 */
class LocalTaskVisibilityHooks {

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    // The Visitors tab never needs to show, on view or edit.
    if ($route_name === 'entity.node.canonical' || $route_name === 'entity.node.edit_form') {
      unset($data['tabs'][0]['visitors.node_tab']);
    }

    // Devel and Revisions only need to show on the node edit page, not the
    // view page.
    if ($route_name === 'entity.node.canonical') {
      unset($data['tabs'][0]['devel.entities:node.devel_tab']);
      unset($data['tabs'][0]['entity.node.version_history']);
      unset($data['tabs'][0]['entity.version_history:node.version_history']);
    }
  }

}
