<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_setup\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\mukurtu_setup\SiteSetupTaskManager;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_setup_update_40201() (#2376).
 *
 * The update hides the new "Migrate from Mukurtu CMS 3" task on existing
 * sites that don't need it, so their finished checklist doesn't come back.
 */
#[Group('mukurtu_setup')]
class MigrationTaskUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'migrate'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node']);

    // mukurtu_setup isn't installed (it pulls in mukurtu_protocol / OG), so
    // provide the service the update calls.
    $this->container->set('mukurtu_setup.task_manager', new SiteSetupTaskManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('config.factory'),
      $this->container->get('state'),
      $this->container->get('current_user'),
    ));

    require_once dirname(__DIR__, 3) . '/mukurtu_setup.install';
  }

  /**
   * Creates a Mukurtu 3 migration id map table holding one row.
   */
  protected function createMigrationMap(int $status): void {
    $this->container->get('database')->schema()->createTable('migrate_map_mukurtu_cms_v3_users', [
      'fields' => [
        'source_ids_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'source_row_status' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['source_ids_hash'],
    ]);
    $this->container->get('database')->insert('migrate_map_mukurtu_cms_v3_users')
      ->fields(['source_ids_hash' => 'abc', 'source_row_status' => $status])
      ->execute();
  }

  /**
   * Creates a node, so the site has content.
   */
  protected function createContent(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Node::create(['type' => 'page', 'title' => 'Existing'])->save();
  }

  /**
   * The task manager the update hook uses.
   */
  protected function taskManager(): SiteSetupTaskManager {
    return $this->container->get('mukurtu_setup.task_manager');
  }

  /**
   * A site that was migrated gets the task marked complete.
   */
  public function testMigratedSiteCompletesTask(): void {
    $this->createMigrationMap(MigrateIdMapInterface::STATUS_IMPORTED);
    $this->createContent();

    $this->assertStringContainsString('complete', (string) mukurtu_setup_update_40201());
    $this->assertTrue($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));
    $this->assertFalse($this->taskManager()->isDismissed(SiteSetupTaskManager::TASK_MIGRATE));
  }

  /**
   * A map with only failed rows isn't a migration; content still dismisses.
   */
  public function testFailedMapRowsDoNotCountAsMigrated(): void {
    $this->createMigrationMap(MigrateIdMapInterface::STATUS_FAILED);
    $this->createContent();

    mukurtu_setup_update_40201();
    $this->assertFalse($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));
    $this->assertTrue($this->taskManager()->isDismissed(SiteSetupTaskManager::TASK_MIGRATE));
  }

  /**
   * A site with content that never migrated gets the task dismissed.
   */
  public function testSiteWithContentDismissesTask(): void {
    $this->createContent();

    $this->assertStringContainsString('Dismissed', (string) mukurtu_setup_update_40201());
    $this->assertTrue($this->taskManager()->isDismissed(SiteSetupTaskManager::TASK_MIGRATE));
    $this->assertFalse($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));
  }

  /**
   * An empty site could still migrate, so it keeps the task.
   */
  public function testEmptySiteKeepsTask(): void {
    $this->assertNull(mukurtu_setup_update_40201());
    $this->assertFalse($this->taskManager()->isDismissed(SiteSetupTaskManager::TASK_MIGRATE));
    $this->assertFalse($this->taskManager()->isComplete(SiteSetupTaskManager::TASK_MIGRATE));
  }

}
