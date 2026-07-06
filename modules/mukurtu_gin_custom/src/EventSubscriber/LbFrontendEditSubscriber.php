<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stamps each rendered LB block with its component UUID.
 *
 * Runs after BlockComponentRenderArray (priority 100) so the build array is
 * already fully assembled. The UUID attribute is user-agnostic and safely
 * cached. The per-user edit URLs are passed separately via drupalSettings in
 * mukurtu_gin_custom_page_attachments().
 */
class LbFrontendEditSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority lower than BlockComponentRenderArray (100) so we run after it.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY][] = ['onBuildRender', 0];
    return $events;
  }

  /**
   * Adds data-layout-block-uuid to the block's outer wrapper attributes.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    // Skip the LB editor preview -- the editor manages its own overlays.
    if ($event->inPreview()) {
      return;
    }

    $build = $event->getBuild();
    if (empty($build)) {
      return;
    }

    $plugin = $event->getPlugin();
    if (!$plugin instanceof BlockPluginInterface) {
      return;
    }

    // Skip field blocks and extra field blocks.
    $plugin_id = $plugin->getPluginId();
    if (str_starts_with($plugin_id, 'field_block:') || str_starts_with($plugin_id, 'extra_field_block:')) {
      return;
    }

    $build['#attributes']['data-layout-block-uuid'] = $event->getComponent()->getUuid();
    $event->setBuild($build);
  }

}
