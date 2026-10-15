<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Gives menu blocks placed by Layout Builder a unique HTML ID.
 */
class MenuBlockIdHooks {

  /**
   * Implements hook_preprocess_block().
   *
   * block--system-menu-block.html.twig builds the ID for the block's heading
   * out of the block's own ID:
   *
   * @code
   * {% set heading_id = attributes.id ~ '-menu'|clean_id %}
   * <nav aria-labelledby="{{ heading_id }}" ...>
   *   <h2{{ title_attributes.setAttribute('id', heading_id) }}>
   * @endcode
   *
   * Core sets that ID in BlockHooks::preprocessBlock(), but only when
   * $variables['elements']['#id'] is non-empty, and that holds the block
   * *config entity's* ID. Blocks placed by Layout Builder have no config
   * entity, so the ID is empty, heading_id collapses to "-menu", and every
   * such block on a page emits the same one. The Mukurtu Dashboard is built
   * from Layout Builder menu blocks, so all 16 of them shared id="-menu" and
   * all 16 aria-labelledby attributes resolved to the first heading on the
   * page: a screen reader announced the Users block as "Communities,
   * cultural protocols, categories" (issue #2395, WCAG 1.3.1 and 4.1.2).
   *
   * Filling the gap here rather than in a template override fixes it for
   * every theme at once, which matters because the dashboard renders in the
   * admin theme while front-end Layout Builder pages can place the same
   * block type.
   *
   * Scoped to menu blocks because they are the ones whose template derives a
   * referenced ID from it. Other Layout Builder blocks are left without an
   * ID, exactly as core leaves them.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    if (($variables['base_plugin_id'] ?? NULL) !== 'system_menu_block') {
      return;
    }
    if (!empty($variables['attributes']['id'])) {
      return;
    }

    // The derivative is the menu name, so the ID reads like core's. Two
    // blocks showing the same menu would derive the same name, which is why
    // this goes through Html::getUniqueId(): it tracks what it has already
    // handed out this request and suffixes any repeat.
    $name = $variables['derivative_plugin_id'] ?? $variables['plugin_id'] ?? 'menu';
    $variables['attributes']['id'] = Html::getUniqueId('block-' . $name);
  }

}
