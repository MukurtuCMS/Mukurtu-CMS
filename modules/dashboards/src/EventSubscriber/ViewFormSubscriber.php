<?php

namespace Drupal\dashboards\EventSubscriber;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes form from views.
 *
 * @package Drupal\site_custom\EventSubscriber
 */
class ViewFormSubscriber implements EventSubscriberInterface, TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
        -100,
      ],
    ];
  }

  /**
   * Post render callback.
   */
  public static function postRender($markup, array $element) {
    $markup = str_replace('<form ', '<div ', $markup);
    $markup = str_replace('</form>', '</div> ', $markup);
    return $markup;
  }

  /**
   * Event render function.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    if (!$event->inPreview()) {
      return;
    }
    $build = $event->getBuild();
    if (!isset($build['content']['#lazy_builder'])) {
      $build['content']['#post_render'] = [
        static::class . '::postRender',
      ];
      $event->setBuild($build);
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return [
      'postRender',
    ];
  }

}
