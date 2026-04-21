<?php

namespace Drupal\layout_builder_restrictions\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\layout_builder\Controller\MoveBlockController as MoveBlockControllerCore;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Defines a controller to move a block.
 *
 * @phpstan-ignore-line
 * @internal
 *   Controller classes are internal.
 */
class MoveBlockController extends MoveBlockControllerCore {

  /**
   * Called if a block move fails layout_builder_restriction validation.
   *
   * @see \Drupal\layout_builder\Controller\MoveBlockController::move()
   */
  public function restrictLayout(SectionStorageInterface $section_storage, $error) {
    $response = new AjaxResponse();
    $layout = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
    ];
    // Revert layout and present error dialog.
    $response->addCommand(new ReplaceCommand('#layout-builder', $layout));
    $response->addCommand(new OpenDialogCommand("#layout-builder-restrictions-error", "Content cannot be placed.", $error));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function build(SectionStorageInterface $section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    // Retrieve defined Layout Builder Restrictions plugins.
    // @phpstan-ignore-line
    $layout_builder_restrictions_manager = \Drupal::service('plugin.manager.layout_builder_restriction');
    $restriction_plugins = $layout_builder_restrictions_manager->getSortedPlugins();
    foreach (array_keys($restriction_plugins) as $id) {
      $plugin = $layout_builder_restrictions_manager->createInstance($id);
      $block_status = $plugin->blockAllowedinContext($section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid);
      if ($block_status !== TRUE) {
        return $this->restrictLayout($section_storage, $block_status);
      }
    }
    return parent::build($section_storage, $delta_from, $delta_to, $region_to, $block_uuid, $preceding_block_uuid);
  }

}
