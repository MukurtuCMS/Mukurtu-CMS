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
      // field_block plugins (e.g. body, title).
      'Content fields',
      // inline_block plugins created on this entity.
      'Inline blocks',
      // Reusable block_content library blocks.
      'Content block',
    ];

    foreach ($definitions as $plugin_id => $definition) {
      $category = (string) ($definition['category'] ?? '');
      if (!in_array($category, $allowed_categories, TRUE)) {
        unset($definitions[$plugin_id]);
      }
    }
  }

}
