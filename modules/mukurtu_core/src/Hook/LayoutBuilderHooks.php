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
   * Implements hook_plugin_filter_block__layout_builder_alter().
   *
   * For basic pages only, administrators see every block except reusable
   * content blocks, the footer, and the "Custom" plugin blocks, and every other
   * role is restricted to the curated set of inline block types. Other content
   * types (landing pages etc.) manage their own restrictions via
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

    $allowed_categories = [
      // inline_block plugins created on this entity.
      'Inline blocks',
    ];

    foreach ($definitions as $plugin_id => $definition) {
      if (!in_array($this->untranslatedCategory($definition), $allowed_categories, TRUE)) {
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
