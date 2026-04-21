<?php

namespace Drupal\Tests\migrate_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests UI rendering of a migration using shared configuration.
 *
 * @group migrate_tools
 */
class MigrateToolsSharedConfigUiTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'node',
    'field',
    'text',
    'user',
  ];

  /**
   * Theme used for UI rendering in tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer migrations',
      'access administration pages',
    ]);

    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'page']);
    $this->createSharedMigrationGroup();
    $this->createMinimalMigration();
  }

  /**
   * Creates a migration group with shared configuration.
   */
  protected function createSharedMigrationGroup(): void {
    \Drupal::entityTypeManager()->getStorage('migration_group')->create([
      'id' => 'test_group',
      'label' => 'Test Group',
      'shared_configuration' => [
        'source' => [
          'plugin' => 'embedded_data',
          'data_rows' => [
            ['id' => 1, 'title' => 'Test node'],
          ],
          'ids' => ['id' => ['type' => 'integer']],
        ],
        'destination' => ['plugin' => 'entity:node'],
        'process' => [
          'title' => 'title',
        ],
      ],
    ])->save();
  }

  /**
   * Creates a migration that relies entirely on shared group config.
   */
  protected function createMinimalMigration(): void {
    \Drupal::entityTypeManager()->getStorage('migration')->create([
      'id' => 'test_shared_migration',
      'label' => 'Test Shared Migration',
      'migration_group' => 'test_group',
      'migration_tags' => [],
      'migration_dependencies' => [],
    ])->save();
  }

  /**
   * Tests that the migration detail UI loads without fatal errors.
   */
  public function testMigrationOverviewPageLoads(): void {
    $this->drupalGet('/admin/structure/migrate/manage/test_group/migrations/test_shared_migration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Shared Migration');
  }

  /**
   * Tests that the execute form loads and can be submitted.
   */
  public function testExecuteMigrationUi(): void {
    $this->drupalGet('/admin/structure/migrate/manage/test_group/migrations/test_shared_migration/execute');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Execute migration');
    $this->submitForm([], 'Execute');
    $this->assertSession()->pageTextContains("Processed 1 item");
  }

}
