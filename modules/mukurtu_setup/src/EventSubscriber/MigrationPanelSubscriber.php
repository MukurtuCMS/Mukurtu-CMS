<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hides the dashboard's Migration panel once its setup task is done.
 *
 * The panel disappears when the "Migrate from Mukurtu CMS 3" task is
 * complete (a migration finished cleanly, or the task was marked complete)
 * or dismissed. Restoring the task on the Site Setup page brings it back.
 */
class MigrationPanelSubscriber implements EventSubscriberInterface {

  /**
   * The dashboard block that holds the migration links.
   */
  const PANEL_PLUGIN_ID = 'system_menu_block:dashboard-migration';

  public function __construct(protected SiteSetupTaskManager $taskManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY, spelled out
    // so this module doesn't need layout_builder's classes to compile the
    // container. Runs after the dashboard's role check (200) and before the
    // block is built (100).
    return [
      'section_component.build.render_array' => ['onBuildRender', 190],
    ];
  }

  /**
   * Stops the Migration panel from rendering once the task is done.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    // Keep the panel visible while the dashboard layout is being edited, so
    // it can still be found and moved.
    if ($event->inPreview() || $event->getPlugin()->getPluginId() !== self::PANEL_PLUGIN_ID) {
      return;
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags(['mukurtu_setup:tasks']);
    $event->addCacheableDependency($cacheability);

    $task = SiteSetupTaskManager::TASK_MIGRATE;
    if ($this->taskManager->isComplete($task) || $this->taskManager->isDismissed($task)) {
      $event->stopPropagation();
    }
  }

}
