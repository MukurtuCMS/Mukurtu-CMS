<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates node render cache when settings affecting computed fields change.
 */
class ConfigSaveCacheInvalidationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => ['onConfigSave'],
    ];
  }

  /**
   * Invalidates node_view whenever mukurtu.settings is saved.
   *
   * The related content and citation fields are computed from this config
   * object, but do not declare a cache dependency on it. Without this,
   * changes made outside the settings forms (drush, config import) would
   * leave rendered node output stale until something else happened to
   * invalidate the node_view tag.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() === 'mukurtu.settings') {
      Cache::invalidateTags(['node_view']);
    }
  }

}
