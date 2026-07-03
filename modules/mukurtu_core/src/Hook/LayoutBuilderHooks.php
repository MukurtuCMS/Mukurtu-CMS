<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for mukurtu_core layout builder.
 */
class LayoutBuilderHooks {

  /**
   * Implements hook_plugin_filter_block__layout_builder_alter().
   *
   * Administrators see all blocks. Every other role (including mukurtu_manager)
   * is restricted to the curated set of categories appropriate for
   * content editors on basic pages and landing pages.
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function restrictBlocksForRole(array &$definitions, array $extra): void {
    // Administrators see everything -- no filtering needed.
    if (\Drupal::currentUser()->hasRole('administrator')) {
      return;
    }

    $allowed_categories = [
      // inline_block plugins created on this entity.
      'Inline blocks',
    ];

    foreach ($definitions as $plugin_id => $definition) {
      $category = (string) ($definition['category'] ?? '');
      if (!in_array($category, $allowed_categories, TRUE)) {
        unset($definitions[$plugin_id]);
      }
    }

    // Within "Inline blocks", further restrict to the approved block types.
    $allowed_inline_bundles = [
      'basic',
      'media_block',
      'media_carousel_block',
      'featured_content',
      'local_contexts_block',
      'horizontal_divider',
      'call_to_action',
    ];
    foreach ($definitions as $plugin_id => $definition) {
      if (str_starts_with($plugin_id, 'inline_block:')) {
        $bundle = substr($plugin_id, strlen('inline_block:'));
        if (!in_array($bundle, $allowed_inline_bundles, TRUE)) {
          unset($definitions[$plugin_id]);
        }
      }
    }
  }

}
