<?php

declare(strict_types=1);

namespace Drupal\migrate_tools_test\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drush\Commands\DrushCommands;

/**
 * Migrate Tools Test drush commands.
 */
final class MigrateToolsTestCommands extends DrushCommands {

  public function __construct(
    private readonly MigrationPluginManager $migrationPluginManager,
    protected readonly KeyValueFactoryInterface $keyValue,
    protected readonly TimeInterface $time,
    protected readonly TranslationInterface $translation,
  ) {
    parent::__construct();
  }

  /**
   * Run a batch import of fruit terms as a test.
   *
   * @command migrate:batch-import-fruit
   */
  public function batchImportFruit(): void {
    $fruit_migration = $this->migrationPluginManager->createInstance('fruit_terms');
    $executable = new MigrateBatchExecutable(
      $fruit_migration,
      new MigrateMessage(),
      $this->keyValue,
      $this->time,
      $this->translation,
      $this->migrationPluginManager
    );
    $executable->batchImport();
    drush_backend_batch_process();
  }

}
