<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_setup\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Site operations" checklist group added for issue #411.
 *
 * The task manager is exercised directly rather than installing the full
 * mukurtu_setup module (which pulls in mukurtu_protocol / OG); the site
 * operations checks only need entity_type.manager, config.factory and state.
 *
 * @see \Drupal\mukurtu_setup\SiteSetupTaskManager
 */
#[Group('mukurtu_setup')]
class SiteOperationsTasksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  protected function taskManager(): SiteSetupTaskManager {
    return new SiteSetupTaskManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('state'),
    );
  }

  /**
   * Returns [id => group] for every defined task.
   */
  protected function taskGroups(): array {
    $map = [];
    foreach ($this->taskManager()->getTasks() as $task) {
      $map[$task->getId()] = $task->getGroup();
    }
    return $map;
  }

  /**
   * The six site operations tasks are defined in their own group.
   */
  public function testSiteOperationsGroupIsDefined(): void {
    $groups = $this->taskManager()->getTaskGroups();
    $this->assertArrayHasKey(SiteSetupTaskManager::GROUP_SITE_OPERATIONS, $groups);

    $expected = ['set_cron', 'web_analytics', 'private_files', 'trusted_hosts', 'cookie_consent', 'bot_protection'];
    $this->assertSame($expected, array_keys($groups[SiteSetupTaskManager::GROUP_SITE_OPERATIONS]));

    foreach ($expected as $id) {
      $this->assertSame(SiteSetupTaskManager::GROUP_SITE_OPERATIONS, $this->taskGroups()[$id]);
    }
  }

  /**
   * Cron is complete only when it has run within the last day.
   */
  public function testCronDetection(): void {
    $manager = $this->taskManager();

    $this->container->get('state')->set('system.cron_last', 0);
    $this->assertFalse($manager->isComplete('set_cron'));

    $this->container->get('state')->set('system.cron_last', time() - (SiteSetupTaskManager::CRON_MAX_AGE + 60));
    $this->assertFalse($manager->isComplete('set_cron'));

    $this->container->get('state')->set('system.cron_last', time() - 60);
    $this->assertTrue($manager->isComplete('set_cron'));
  }

  /**
   * Analytics detection is a graceful no when google_tag is not installed.
   */
  public function testAnalyticsDetectionWithoutGoogleTag(): void {
    $this->assertFalse($this->container->get('entity_type.manager')->hasDefinition('google_tag_container'));
    $this->assertFalse($this->taskManager()->isComplete('web_analytics'));
  }

  /**
   * The private file path check follows settings.php.
   */
  public function testPrivateFilePathDetection(): void {
    $this->setSetting('file_private_path', '');
    $this->assertFalse($this->taskManager()->isComplete('private_files'));

    $this->setSetting('file_private_path', 'sites/default/files/private');
    $this->assertTrue($this->taskManager()->isComplete('private_files'));
  }

  /**
   * Trusted host patterns must be more specific than a catch-all.
   */
  public function testTrustedHostDetection(): void {
    $manager = $this->taskManager();

    $this->setSetting('trusted_host_patterns', []);
    $this->assertFalse($manager->isComplete('trusted_hosts'));

    $this->setSetting('trusted_host_patterns', ['.*']);
    $this->assertFalse($manager->isComplete('trusted_hosts'));

    $this->setSetting('trusted_host_patterns', ['^www\.example\.com$']);
    $this->assertTrue($manager->isComplete('trusted_hosts'));
  }

  /**
   * The two manual tasks never auto-complete but can be marked done.
   */
  public function testManualTasks(): void {
    $manager = $this->taskManager();

    foreach (['cookie_consent', 'bot_protection'] as $id) {
      $this->assertFalse($manager->isComplete($id));
      $manager->markComplete($id);
      $this->assertTrue($manager->isComplete($id));
      $manager->markIncomplete($id);
      $this->assertFalse($manager->isComplete($id));
    }
  }

}
