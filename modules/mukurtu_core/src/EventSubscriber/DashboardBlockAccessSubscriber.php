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
    $roles = $this->currentUser->getRoles();

    $allowed = match ($plugin_id) {
      'system_menu_block:dashboard-migration' =>
        in_array('administrator', $roles, TRUE),
      'system_menu_block:dashboard-site-settings' =>
        !empty(array_intersect(['administrator', 'mukurtu_manager'], $roles)),
      default => TRUE,
    };

    if (!$allowed) {
      $cacheability = new CacheableMetadata();
      $cacheability->addCacheContexts(['user.roles']);
      $event->addCacheableDependency($cacheability);
      $event->stopPropagation();
    }
  }

}
