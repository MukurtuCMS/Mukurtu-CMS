<?php

namespace Drupal\layout_builder_restrictions\Controller;

use Drupal\layout_builder\Controller\ChooseBlockController as ChooseBlockControllerCore;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Defines a controller to choose a new block.
 *
 * @phpstan-ignore-line
 * @internal
 *   Controller classes are internal.
 */
class ChooseBlockController extends ChooseBlockControllerCore {

  /**
   * {@inheritdoc}
   */
  public function build(SectionStorageInterface $section_storage, $delta, $region) {
    $build = parent::build($section_storage, $delta, $region);

    // Retrieve defined Layout Builder Restrictions plugins.
    // @phpstan-ignore-line
    $layout_builder_restrictions_manager = \Drupal::service('plugin.manager.layout_builder_restriction');
    $restriction_plugins = $layout_builder_restrictions_manager->getSortedPlugins();
    foreach (array_keys($restriction_plugins) as $id) {
      $plugin = $layout_builder_restrictions_manager->createInstance($id);
      $allowed_inline_blocks = $plugin->inlineBlocksAllowedinContext($section_storage, $delta, $region);

      // If no inline blocks are allowed, remove the "Create custom block" link.
      if (empty($allowed_inline_blocks)) {
        unset($build['add_block']);
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function inlineBlockList(SectionStorageInterface $section_storage, $delta, $region) {
    $build = parent::inlineBlockList($section_storage, $delta, $region);

    // Retrieve defined Layout Builder Restrictions plugins.
    // @phpstan-ignore-line
    $layout_builder_restrictions_manager = \Drupal::service('plugin.manager.layout_builder_restriction');
    $restriction_plugins = $layout_builder_restrictions_manager->getSortedPlugins();
    foreach (array_keys($restriction_plugins) as $id) {
      $plugin = $layout_builder_restrictions_manager->createInstance($id);
      $allowed_inline_blocks = $plugin->inlineBlocksAllowedinContext($section_storage, $delta, $region);

      // Loop through links and remove those for disallowed inline block types.
      foreach ($build['links']['#links'] as $key => $link) {
        $route_parameters = $link['url']->getRouteParameters();
        if (!in_array($route_parameters['plugin_id'], $allowed_inline_blocks)) {
          unset($build['links']['#links'][$key]);
        }
      }
    }
    return $build;
  }

}
