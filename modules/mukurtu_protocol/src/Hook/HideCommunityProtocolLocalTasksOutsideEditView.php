<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Restricts the Revisions/Devel local tasks to the community/protocol edit view.
 */
class HideCommunityProtocolLocalTasksOutsideEditView {

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    $this->alterEntityTabs($data, $route_name, 'community');
    $this->alterEntityTabs($data, $route_name, 'protocol');
  }

  /**
   * Hides Revisions/Devel outside the edit-view subtree for one entity type.
   *
   * Devel is hidden everywhere except the edit-view subtree. Revisions is
   * hidden everywhere except the edit-view subtree, and even there only uid 1
   * sees it, preserving the site's existing uid-1-only restriction on
   * Revisions.
   */
  protected function alterEntityTabs(&$data, $route_name, string $entity_type_id): void {
    $edit_view_routes = [
      "entity.$entity_type_id.edit_form",
      "entity.$entity_type_id.version_history",
      "entity.$entity_type_id.devel_load",
      "entity.$entity_type_id.devel_render",
      "entity.$entity_type_id.devel_definition",
      "entity.$entity_type_id.devel_path_alias",
      "entity.$entity_type_id.devel_load_with_references",
    ];
    $in_edit_view = in_array($route_name, $edit_view_routes, TRUE);

    if (!$in_edit_view) {
      unset($data['tabs'][0]["devel.entities:$entity_type_id.devel_tab"]);
    }

    // Drupal core derives a second, duplicate Revisions tab
    // ("entity.version_history:{type}.version_history") alongside our custom
    // route provider's own version_history task, both pointing at the same
    // page. Always drop ours so only one Revisions tab can ever render.
    unset($data['tabs'][0]["entity.$entity_type_id.version_history"]);

    if (!$in_edit_view || \Drupal::currentUser()->id() != 1) {
      unset($data['tabs'][0]["entity.version_history:$entity_type_id.version_history"]);
    }
  }

}
