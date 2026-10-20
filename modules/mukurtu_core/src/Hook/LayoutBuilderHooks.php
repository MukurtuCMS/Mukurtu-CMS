<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for mukurtu_core layout builder.
 */
class LayoutBuilderHooks {

  /**
   * Blocks that can be added to basic pages, for every role.
   *
   * Matches the landing page allowlists in
   * core.entity_view_display.node.landing_page.default, so editors get the
   * same blocks on both content types. Reusable content blocks are left out:
   * content blocks come from "Add a block" (a fresh entity per placement), so
   * the same block types do not appear in two lists and one entity is never
   * shared across pages by accident.
   */
  public const ALLOWED_BLOCKS = [
    'inline_block:basic',
    'inline_block:call_to_action',
    'inline_block:featured_content',
    'inline_block:full_image_with_description',
    'inline_block:horizontal_divider',
    'inline_block:image_with_description',
    'inline_block:local_contexts_block',
    'inline_block:media_carousel_block',
    'inline_block:vertical_image_with_description',
    'views_block:mukurtu_browse_by_map-map_block',
    'views_block:mukurtu_categories-browse_by_category_block',
    'views_block:mukurtu_categories-browse_by_category_names_block',
    'mukurtu_browse_by_community',
  ];

  /**
   * Implements hook_plugin_filter_block__layout_builder_alter().
   *
   * Limits basic pages to self::ALLOWED_BLOCKS. Other content types (landing
   * pages etc.) manage their own restrictions via layout_builder_restrictions
   * config and are not altered here.
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function restrictBasicPageBlocks(array &$definitions, array $extra): void {
    if (!isset($extra['section_storage']) || !$extra['section_storage'] instanceof OverridesSectionStorageInterface) {
      return;
    }
    $entity = $extra['section_storage']->getContextValue('entity');
    if (!($entity instanceof NodeInterface) || $entity->bundle() !== 'page') {
      return;
    }

    $definitions = array_intersect_key($definitions, array_flip(self::ALLOWED_BLOCKS));
  }

}
