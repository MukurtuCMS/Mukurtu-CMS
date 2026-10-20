<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for mukurtu_core layout builder.
 */
class LayoutBuilderHooks {

  /**
   * Blocks non-administrators can add to basic pages.
   *
   * Matches the landing page allowlists in
   * core.entity_view_display.node.landing_page.default, so editors get the
   * same blocks on both content types.
   */
  public const NON_ADMIN_BLOCKS = [
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
   * For basic pages only, administrators see every block except reusable
   * content blocks, the footer, and the "Custom" plugin blocks, and every other
   * role gets the blocks landing pages offer (self::NON_ADMIN_BLOCKS). Other
   * content types (landing pages etc.) manage their own restrictions via
   * layout_builder_restrictions config and are not altered here.
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function restrictBlocksForRole(array &$definitions, array $extra): void {
    // Only restrict on basic pages. Other content types (e.g. landing pages)
    // have their own restrictions configured via layout_builder_restrictions.
    if (isset($extra['section_storage']) && $extra['section_storage'] instanceof OverridesSectionStorageInterface) {
      $entity = $extra['section_storage']->getContextValue('entity');
      if (!($entity instanceof NodeInterface) || $entity->bundle() !== 'page') {
        return;
      }
    }
    else {
      return;
    }

    // Hide these from every role:
    // - Reusable content blocks. Content blocks come from "Add a block" (a
    //   fresh entity per placement), so the same block types do not appear in
    //   two lists, and one entity is never shared across pages by accident.
    // - The "Custom" category, which holds site-wide plugin blocks (the footer
    //   and the consent popup) that live in theme regions, not page bodies.
    // - The footer and single-media inline types, which landing pages do not
    //   offer either.
    foreach ($definitions as $plugin_id => $definition) {
      if (str_starts_with($plugin_id, 'block_content:') || $this->untranslatedCategory($definition) === 'Custom') {
        unset($definitions[$plugin_id]);
      }
    }
    unset($definitions['inline_block:mukurtu_footer'], $definitions['inline_block:media_block']);

    // Administrators see everything else -- no further filtering needed.
    if (\Drupal::currentUser()->hasRole('administrator')) {
      return;
    }

    // Everyone else gets the same blocks landing pages offer.
    $definitions = array_intersect_key($definitions, array_flip(self::NON_ADMIN_BLOCKS));
  }

  /**
   * Returns a block definition's category in English.
   *
   * Casting a TranslatableMarkup category to a string translates it, so a
   * comparison against the English name fails on a non-English interface.
   */
  protected function untranslatedCategory(array $definition): string {
    $category = $definition['category'] ?? '';
    return $category instanceof TranslatableMarkup ? $category->getUntranslatedString() : (string) $category;
  }

}
