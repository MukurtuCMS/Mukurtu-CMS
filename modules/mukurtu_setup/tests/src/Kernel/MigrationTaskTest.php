<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_setup\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\SectionComponent;
use Drupal\mukurtu_setup\EventSubscriber\MigrationPanelSubscriber;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Drupal\system\Entity\Menu;
use Drupal\Core\Session\UserSession;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Migrate from Mukurtu CMS 3" task and the Migration panel (#2376).
 *
 * As in SiteOperationsTasksTest, the task manager is built directly rather
 * than installing mukurtu_setup, which pulls in mukurtu_protocol / OG.
 *
 * @see \Drupal\mukurtu_setup\SiteSetupTaskManager
 * @see \Drupal\mukurtu_setup\EventSubscriber\MigrationPanelSubscriber
 */
#[Group('mukurtu_setup')]
class MigrationTaskTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'contextual',
    'layout_discovery',
    'layout_builder',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The system_menu_block derivative for the panel only exists while its
    // menu does.
    Menu::create(['id' => 'dashboard-migration', 'label' => 'Migration'])->save();
  }

  /**
   * Builds a task manager for a user with the given roles.
   */
  protected function taskManager(array $roles = ['authenticated', 'administrator']): SiteSetupTaskManager {
    return new SiteSetupTaskManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('state'),
      new UserSession(['uid' => 2, 'roles' => $roles]),
    );
  }

  /**
   * Whether the panel subscriber stops the given dashboard block rendering.
   */
  protected function panelHidden(string $plugin_id, bool $in_preview = FALSE): bool {
    $component = new SectionComponent('a3f0c6de-0000-4000-8000-000000000001', 'two', ['id' => $plugin_id]);
    $event = new SectionComponentBuildRenderArrayEvent($component, [], $in_preview);
    (new MigrationPanelSubscriber($this->taskManager()))->onBuildRender($event);
    return $event->isPropagationStopped();
  }

  /**
   * The task leads the Required group and can be dismissed.
   */
  public function testTaskDefinition(): void {
    $required = $this->taskManager()->getTaskGroups()[SiteSetupTaskManager::GROUP_REQUIRED];
    $this->assertSame(SiteSetupTaskManager::TASK_MIGRATE, array_key_first($required));

    $task = $required[SiteSetupTaskManager::TASK_MIGRATE];
    $this->assertTrue($task->isDismissible());
    $this->assertTrue($task->canAutoDetect());
    $this->assertSame('/admin/migrate', $task->getActionUrl());
  }

  /**
   * Only administrators see the task, and it only counts for them.
   */
  public function testTaskIsAdministratorOnly(): void {
    $admin = $this->taskManager();
    $manager = $this->taskManager(['authenticated', 'mukurtu_manager']);

    $ids = fn(SiteSetupTaskManager $m) => array_map(fn($t) => $t->getId(), $m->getTasks());
    $this->assertContains(SiteSetupTaskManager::TASK_MIGRATE, $ids($admin));
    $this->assertNotContains(SiteSetupTaskManager::TASK_MIGRATE, $ids($manager));

    $this->assertSame($manager->getCounts()['total'] + 1, $admin->getCounts()['total']);

    // Tasks without a role restriction are shared by both.
    $this->assertContains('create_community', $ids($manager));
  }

  /**
   * The task completes only once a migration has finished cleanly.
   */
  public function testCompletionFollowsMigrationState(): void {
    $this->assertFalse($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));

    $this->container->get('state')->set(SiteSetupTaskManager::STATE_MIGRATION_SUCCEEDED, TRUE);
    $this->assertTrue($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));
  }

  /**
   * The panel shows until the task is done, and hides once it is.
   */
  public function testPanelFollowsTask(): void {
    $panel = MigrationPanelSubscriber::PANEL_PLUGIN_ID;
    $this->assertFalse($this->panelHidden($panel));

    $this->taskManager()->dismiss(SiteSetupTaskManager::TASK_MIGRATE);
    $this->assertTrue($this->panelHidden($panel));

    $this->taskManager()->restore(SiteSetupTaskManager::TASK_MIGRATE);
    $this->assertFalse($this->panelHidden($panel));

    $this->taskManager()->markComplete(SiteSetupTaskManager::TASK_MIGRATE);
    $this->assertTrue($this->panelHidden($panel));

    $this->taskManager()->markIncomplete(SiteSetupTaskManager::TASK_MIGRATE);
    $this->container->get('state')->set(SiteSetupTaskManager::STATE_MIGRATION_SUCCEEDED, TRUE);
    $this->assertTrue($this->panelHidden($panel));
  }

  /**
   * The subscriber leaves other blocks and the layout editor alone.
   */
  public function testPanelSubscriberScope(): void {
    $this->taskManager()->dismiss(SiteSetupTaskManager::TASK_MIGRATE);

    $this->assertFalse($this->panelHidden(MigrationPanelSubscriber::PANEL_PLUGIN_ID, TRUE));

    Menu::create(['id' => 'dashboard-users', 'label' => 'Users'])->save();
    $this->assertFalse($this->panelHidden('system_menu_block:dashboard-users'));
  }

  /**
   * The panel's render cache is tagged so task changes reach it.
   */
  public function testPanelCacheTag(): void {
    $component = new SectionComponent('a3f0c6de-0000-4000-8000-000000000002', 'two', ['id' => MigrationPanelSubscriber::PANEL_PLUGIN_ID]);
    $event = new SectionComponentBuildRenderArrayEvent($component, []);
    (new MigrationPanelSubscriber($this->taskManager()))->onBuildRender($event);
    $this->assertContains('mukurtu_setup:tasks', $event->getCacheableMetadata()->getCacheTags());
  }

}
