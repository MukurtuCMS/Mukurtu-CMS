<?php

namespace Drupal\dashboards\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes form from views.
 *
 * @package Drupal\dashboards_gin\EventSubscriber
 */
class Decorator implements EventSubscriberInterface {

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
   * Event render function.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $contexts = $event->getContexts();
    if (!isset($contexts['entity'])
        || !$contexts["entity"]->getContextData()->getEntity()
        || "dashboard" != $contexts["entity"]->getContextData()->getEntity()->getEntityTypeId()) {
      return;
    }
    $build = $event->getBuild();
    $build['#attributes']['class'][] = 'panel';
    $build['#attributes']['class'][] = 'dashboard-gin-panel';

    if (!isset($build['content']['#lazy_builder']) && $event->getComponent()->getPluginId() == 'shortcuts') {
      $build['content']['#theme'] = 'dashboards_admin_shortcuts';
      if (isset($build['content'][0]['#links'])) {
        $build['content']['#list'] = $build['content'][0]['#links'];
      }
    }

    $event->setBuild($build);

  }

}
