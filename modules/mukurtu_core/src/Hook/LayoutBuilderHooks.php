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
   * Implements hook_plugin_filter_block__layout_builder_alter().
   *
   * Administrators see all blocks. For basic pages only, every other role is
   * restricted to the curated set of inline block types. Other content types
   * (landing pages etc.) manage their own restrictions via layout_builder_restrictions
   * config and are not altered here.
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

    // These block_content bundles are only meant to be placed via "Create
    // custom block" (a fresh, non-reusable entity per placement). Strip any
    // already-saved reusable instance of them from the "Content block"
    // library so editors -- including administrators -- can't accidentally
    // place the same entity in more than one spot.
    $no_reuse_bundles = [
      'media_carousel_block',
      'featured_content',
      'local_contexts_block',
      'horizontal_divider',
      'call_to_action',
    ];
    $block_content_storage = \Drupal::entityTypeManager()->getStorage('block_content');
    foreach ($definitions as $plugin_id => $definition) {
      if (str_starts_with($plugin_id, 'block_content:')) {
        $block_uuid = substr($plugin_id, strlen('block_content:'));
        $block_content = $block_content_storage->loadByProperties(['uuid' => $block_uuid]);
        $block_content = $block_content ? reset($block_content) : NULL;
        if ($block_content && in_array($block_content->bundle(), $no_reuse_bundles, TRUE)) {
          unset($definitions[$plugin_id]);
        }
      }
    }

    // Administrators see everything else -- no further filtering needed.
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
