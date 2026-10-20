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
   * For basic pages only, administrators see every block except the footer and
   * the "Custom" plugin blocks, and every other role is restricted to the
   * curated set of inline block types. Other content types
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
    // place the same entity in more than one spot. The footer entity belongs
    // in the footer region only, where the mukurtu_footer plugin renders it.
    $no_reuse_bundles = [
      'media_carousel_block',
      'featured_content',
      'local_contexts_block',
      'horizontal_divider',
      'call_to_action',
      'mukurtu_footer',
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

    // Hide these from every role. The "Custom" category holds site-wide
    // plugin blocks (the footer and the consent popup) that live in theme
    // regions, not page bodies. The footer and single-media inline types are
    // not offered on landing pages either.
    foreach ($definitions as $plugin_id => $definition) {
      if ($this->untranslatedCategory($definition) === 'Custom') {
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
