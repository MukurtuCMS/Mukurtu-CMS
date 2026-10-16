<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives menu blocks placed with Layout Builder a unique id attribute.
 *
 * The block--system-menu-block.html.twig template builds the heading id, which
 * the nav's aria-labelledby points at, from the block's id attribute. Layout
 * Builder components have no id, so every menu block on a layout got the
 * heading id "-menu", and screen readers could announce the wrong section name
 * (#2395). The id comes from the component UUID rather than
 * Html::getUniqueId(), so it is the same on every render and safe to cache.
 */
class LayoutBuilderMenuBlockIdSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Lower than BlockComponentRenderArray (100), so the build already exists.
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['onBuildRender', 0],
    ];
  }

  /**
   * Sets an id on menu block components that don't have one.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    if (!str_starts_with($event->getPlugin()->getPluginId(), 'system_menu_block:')) {
      return;
    }

    $build = $event->getBuild();
    if (empty($build) || !empty($build['#attributes']['id'])) {
      return;
    }

    $build['#attributes']['id'] = Html::getId('block-' . $event->getComponent()->getUuid());
    $event->setBuild($build);
  }

}
