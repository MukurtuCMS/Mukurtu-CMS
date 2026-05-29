<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Restricts specific dashboard panels by role.
 */
class DashboardBlockAccessSubscriber implements EventSubscriberInterface {

  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['onBuildRender', 200],
    ];
  }

  /**
   * Denies rendering of role-restricted dashboard panels.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $plugin_id = $event->getPlugin()->getPluginId();

    $restricted = [
      'system_menu_block:dashboard-migration' =>
        in_array('administrator', $this->currentUser->getRoles(), TRUE),
      'system_menu_block:dashboard-site-settings' =>
        !empty(array_intersect(['administrator', 'mukurtu_manager'], $this->currentUser->getRoles())),
    ];

    if (!array_key_exists($plugin_id, $restricted)) {
      return;
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['user.roles']);
    $event->addCacheableDependency($cacheability);

    if (!$restricted[$plugin_id]) {
      $event->stopPropagation();
    }
  }

}
