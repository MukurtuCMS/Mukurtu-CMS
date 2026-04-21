<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\FilterDefaultField;
use Drush\Attributes\DefaultFields;
use Drush\Attributes\Usage;
use Drush\Attributes\ValidateModulesEnabled;
use Drush\Attributes\Topics;
use Drush\Attributes\Option;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Graph\Graph;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drupal\migrate_tools\DrushLogMigrateMessage;
use Drupal\migrate_tools\EventSubscriber\MigrationDrushCommandProgress;
use Drupal\migrate_tools\IdMapFilter;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate_tools\MigrateTools;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrate Tools drush commands.
 *
 * @phpstan-consistent-constructor
 */
class MigrateToolsCommands extends DrushCommands {

  /**
   * Migrate message logger.
   */
  protected ?DrushLogMigrateMessage $migrateMessage = NULL;

  public function __construct(
    protected readonly MigrationPluginManager $migrationPluginManager,
    protected readonly DateFormatter $dateFormatter,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly KeyValueFactoryInterface $keyValue,
    protected readonly TimeInterface $time,
    protected readonly TranslationInterface $translation,
    protected readonly MigrationDrushCommandProgress $commandProgress,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('keyvalue'),
      $container->get('datetime.time'),
      $container->get('string_translation'),
      $container->get('migrate_tools.migration_drush_command_progress'),
    );
  }

  /**
   * Shows a tree of migration dependencies.
   *
   * @param string $migration_names
   *   Restrict to a comma-separated list of migrations (Optional).
   * @param array $options
   *   Additional options for the command.
   *
   * @command migrate:tree
   */
  #[Command(name: 'migrate:tree')]
  #[Argument(name: 'migration_names', description: 'Restrict to a comma-separated list of migrations (Optional).')]
  #[Option(name: 'all', description: 'Process all migrations')]
  #[Option(name: 'group', description: 'A comma-separated list of migration groups to import')]
  #[Option(name: 'tag', description: 'A comma-separated list of migration tags to import')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function dependencyTree(
    $migration_names = '',
    array $options = [
      'all' => FALSE,
      'group' => self::REQ,
      'tag' => self::REQ,
    ],
  ): void {
    $manager = $this->migrationPluginManager;
    $migrations = $this->migrationsList($migration_names, $options);

    // Turn this into a flat array.
    $migrations_to_process = [];
    foreach ($migrations as $group_migrations) {
      foreach ($group_migrations as $migration) {
        $migrations_to_process[$migration->id()] = $migration;
      }
    }

    // Create a dependency graph. The migrations in the given list may have
    // dependencies not in the list, so we need to add those to the list as we
    // go.
    $dependency_graph = [];
    while (!empty($migrations_to_process)) {
      // Get the next migration in the list.
      $migration = reset($migrations_to_process);
      unset($migrations_to_process[$migration->id()]);

      // Add its dependencies to the graph and to the list.
      $migration_dependencies = $migration->getMigrationDependencies();

      $dependency_graph[$migration->id()]['edges'] = [];
      if (isset($migration_dependencies['required'])) {
        foreach ($migration_dependencies['required'] as $dependency) {
          $dependency_graph[$migration->id()]['edges'][$dependency] = $dependency;

          $migrations_to_process[$dependency] = $manager->createInstance($dependency);
        }
      }
    }

    $dependency_graph = (new Graph($dependency_graph))->searchAndSort();

    // Get the list of top-level migrations, that is, those that nothing depends
    // on.
    $top_level_migrations = [];
    foreach ($dependency_graph as $migration_name => $vertex) {
      if (empty($vertex['reverse_paths'])) {
        $top_level_migrations[] = $migration_name;
      }
    }

    foreach ($top_level_migrations as $migration_name) {
      $this->output()->writeln($migration_name);
      $this->printDependencies(0, '', $dependency_graph, $migration_name);
    }
  }

  /**
   * Prints the dependencies of a single migration in the dependency tree.
   *
   * Helper for dependencyTree().
   *
   * @param int $level
   *   The current level in the tree.
   * @param string $prefix
   *   The prefix for the current level's lines.
   * @param array $dependency_graph
   *   The complete dependency graph.
   * @param string $migration_name
   *   The name of the migration to print dependencies for.
   */
  protected function printDependencies($level, $prefix, array $dependency_graph, $migration_name): void {
    $last_edge = end($dependency_graph[$migration_name]['edges']);

    foreach ($dependency_graph[$migration_name]['edges'] as $edge) {
      if ($edge === $last_edge) {
        $tree_string = '└──';
        $subtree_prefix = $prefix . '   ';
      }
      else {
        $tree_string = '├──';
        $subtree_prefix = $prefix . '│  ';
      }
      $this->output()->writeln($prefix . $tree_string . $edge);

      $this->printDependencies($level + 1, $subtree_prefix, $dependency_graph, $edge);
    }
  }

  /**
   * List all migrations with current status.
   *
   * @param string $migration_names
   *   Restrict to a comma-separated list of migrations (Optional).
   * @param array $options
   *   Additional options for the command.
   *
   * @command migrate:status
   *
   * @option group A comma-separated list of migration groups to list
   * @option tag Name of the migration tag to list
   * @option names-only Only return names, not all the details (faster)
   * @option continue-on-failure When a migration fails, continue processing
   *   remaining migrations.
   *
   * @default $options []
   *
   * @usage migrate:status
   *   Retrieve status for all migrations
   * @usage migrate:status --group=beer
   *   Retrieve status for all migrations in a given group
   * @usage migrate:status --tag=user
   *   Retrieve status for all migrations with a given tag
   * @usage migrate:status --group=beer --tag=user
   *   Retrieve status for all migrations in the beer group
   *   and with the user tag.
   * @usage migrate:status beer_term,beer_node
   *   Retrieve status for specific migrations
   *
   * @validate-module-enabled migrate_tools
   *
   * @aliases ms, migrate-status
   *
   * @field-labels
   *   group: Group
   *   id: Migration ID
   *   status: Status
   *   total: Total
   *   imported: Imported
   *   unprocessed: Unprocessed
   *   message_count: Message Count
   *   last_imported: Last Imported
   * @default-fields group,id,status,total,imported,unprocessed,message_count,last_imported
   *
   *   Migrations status formatted as table.
   */
  #[Command(name: 'migrate:status', aliases: ['ms', 'migrate-status'])]
  #[Argument(name: 'migration_names', description: 'Restrict to a comma-separated list of migrations. Optional.')]
  #[Option(name: 'group', description: 'A comma-separated list of migration groups to import')]
  #[Option(name: 'tag', description: 'Name of the migration tag to list')]
  #[Option(name: 'names-only', description: 'Only return names, not all the details (faster)')]
  #[Option(name: 'continue-on-failure', description: 'When a migration fails, continue processing remaining migrations.')]
  #[Usage(name: 'migrate:status', description: 'Retrieve status for all migrations')]
  #[Usage(name: 'migrate:status --group=beer', description: 'Retrieve status for all migrations, grouped by tag')]
  #[Usage(name: 'migrate:status --tag=user', description: 'Retrieve status for all migrations tagged with user.')]
  #[Usage(name: 'migrate:status beer_term,beer_node', description: 'Retrieve status for specific migrations')]
  #[Usage(name: 'migrate:status --field=id', description: 'Retrieve a raw list of migration IDs.')]
  #[Usage(name: 'ms --fields=id,status --format=json', description: 'Retrieve a JSON serialized list of migrations, each item containing only the migration ID and its status.')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  // phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
  #[FieldLabels(labels: ['group' => 'Group', 'id' => 'Migration ID', 'status' => 'Status', 'total' => 'Total', 'imported' => 'Imported', 'unprocessed' => 'Unprocessed', 'message_count' => 'Message Count', 'last_imported' => 'Last Imported'])]  // phpcs:ignore
  #[DefaultFields(fields: ['group', 'id', 'status', 'total', 'imported', 'unprocessed', 'message_count', 'last_imported'])]
  // phpcs:enable
  #[FilterDefaultField(field: 'status')]
  // phpcs:disable Drupal.Commenting.FunctionComment.Missing, Squiz.WhiteSpace.FunctionSpacing.Before
  public function status(
    $migration_names = '',
    array $options = [
      'group' => self::REQ,
      'tag' => self::REQ,
      'names-only' => FALSE,
      'continue-on-failure' => FALSE,
    ],
  ): RowsOfFields {
  // phpcs:enable
    $names_only = $options['names-only'];

    $migrations = $this->migrationsList($migration_names, $options);

    $table = [];
    $errors = [];
    // Take it one group at a time, listing the migrations within each group.
    $group_entity_exists = $this->entityTypeManager->hasHandler('migration_group', 'storage');
    $group_name = 'Default (default)';
    foreach ($migrations as $group_id => $migration_list) {
      if ($group_entity_exists) {
        /** @var \Drupal\migrate_plus\Entity\MigrationGroup $group */
        $group = $this->entityTypeManager->getStorage('migration_group')->load($group_id);
        $group_name = !empty($group) ? "{$group->label()} ({$group->id()})" : $group_id;
      }

      foreach ($migration_list as $migration_id => $migration) {
        if ($names_only) {
          $table[] = [
            'group' => \dt('Group: @name', ['@name' => $group_name]),
            'id' => $migration_id,
          ];
        }
        else {
          try {
            $map = $migration->getIdMap();
            $imported = $map->importedCount();
            $source_plugin = $migration->getSourcePlugin();
          }
          catch (\Exception $e) {
            $error = \dt(
              'Failure retrieving information on @migration: @message',
              ['@migration' => $migration_id, '@message' => $e->getMessage()]
            );
            $this->logger()->error($error);
            $errors[] = $error;
            continue;
          }

          try {
            $source_rows = $source_plugin->count();
            // -1 indicates uncountable sources.
            if ($source_rows == -1) {
              $source_rows = \dt('N/A');
              $unprocessed = \dt('N/A');
            }
            else {
              $unprocessed = $source_rows - $map->processedCount();
            }
          }
          catch (\Exception $e) {
            $this->logger()->error(
              \dt(
                'Could not retrieve source count from @migration: @message',
                ['@migration' => $migration_id, '@message' => $e->getMessage()]
              )
            );
            $source_rows = \dt('N/A');
            $unprocessed = \dt('N/A');
          }

          $status = $migration->getStatusLabel();
          $message_count = $map->messageCount();

          $migrate_last_imported_store = $this->keyValue->get(
            'migrate_last_imported'
          );
          $last_imported = $migrate_last_imported_store->get(
            $migration->id(),
            FALSE
          );
          if ($last_imported) {
            $last_imported = $this->dateFormatter->format(
              (int) ($last_imported / 1000),
              'custom',
              'Y-m-d H:i:s'
            );
          }
          else {
            $last_imported = '';
          }

          $table[] = [
            'group' => $group_name,
            'id' => $migration_id,
            'status' => $status,
            'total' => $source_rows,
            'imported' => $imported,
            'unprocessed' => $unprocessed,
            'last_imported' => $last_imported,
            'message_count' => $message_count,
          ];
        }
      }
      if ($group_id !== array_key_last($migrations)) {
        $table[] = [];
      }
    }

    // If any errors occurred, throw an exception.
    if (!empty($errors)) {
      throw new \Exception(implode(PHP_EOL, $errors));
    }

    return new RowsOfFields($table);
  }

  /**
   * Perform one or more migration processes.
   *
   * @param string $migration_names
   *   ID of migration(s) to import. Delimit multiple using commas.
   * @param array $options
   *   Additional options for the command.
   *
   * @command migrate:import
   *
   * @option all Process all migrations.
   * @option group A comma-separated list of migration groups to import
   * @option tag Name of the migration tag to import
   * @option limit Limit on the number of items to process in each migration
   * @option feedback Frequency of progress messages, in items processed
   * @option idlist Comma-separated list of IDs to import
   * @option idlist-delimiter The delimiter for records
   * @option update  In addition to processing unprocessed items from the
   *   source, update previously-imported items with the current data
   * @option force Force an operation to run, even if all dependencies are not
   *   satisfied
   * @option continue-on-failure When a migration fails, continue processing
   *   remaining migrations.
   * @option execute-dependencies Execute all dependent migrations first.
   * @option skip-progress-bar Skip displaying a progress bar.
   * @option sync Sync source and destination. Delete destination records that
   *   do not exist in the source.
   *
   * @default $options []
   *
   * @usage migrate:import --all
   *   Perform all migrations
   * @usage migrate:import --group=beer
   *   Import all migrations in the beer group
   * @usage migrate:import --tag=user
   *   Import all migrations with the user tag
   * @usage migrate:import --group=beer --tag=user
   *   Import all migrations in the beer group and with the user tag
   * @usage migrate:import beer_term,beer_node
   *   Import new terms and nodes
   * @usage migrate:import beer_user --limit=2
   *   Import no more than 2 users
   * @usage migrate:import beer_user --idlist=5
   *   Import the user record with source ID 5
   * @usage migrate:import beer_node_revision --idlist=1:2,2:3,3:5
   *   Import the node revision record with source IDs [1,2], [2,3], and [3,5]
   *
   * @validate-module-enabled migrate_tools
   *
   * @aliases mim, migrate-import
   *
   * @throws \Exception
   *   If there are not enough parameters to the command.
   */
  #[Command(name: 'migrate:import', aliases: ['mim', 'migrate-import'])]
  #[Argument(name: 'migration_names', description: 'ID of migration(s) to import. Delimit multiple using commas.')]
  #[Option(name: 'all', description: 'Process all migrations')]
  #[Option(name: 'group', description: 'A comma-separated list of migration groups to import')]
  #[Option(name: 'tag', description: 'A comma-separated list of migration tags to import')]
  #[Option(name: 'limit', description: 'Limit on the number of items to process in each migration')]
  #[Option(name: 'feedback', description: 'Frequency of progress messages, in items processed')]
  #[Option(name: 'idlist', description: 'Comma-separated list of IDs to import.')]
  #[Option(name: 'idlist-delimiter', description: 'The delimiter for records')]
  #[Option(name: 'update', description: 'In addition to processing unprocessed items from the source, update previously-imported items with the current data')]
  #[Option(name: 'force', description: 'Force an operation to run, even if all dependencies are not satisfied')]
  #[Option(name: 'continue-on-failure', description: 'When a migration fails, continue processing remaining migrations.')]
  #[Option(name: 'execute-dependencies', description: 'Execute all dependent migrations first')]
  #[Option(name: 'skip-progress-bar', description: 'Skip displaying a progress bar.')]
  #[Option(name: 'sync', description: 'Sync source and destination. Delete destination records that do not exist in the source.')]
  #[Usage(name: 'migrate:import --all', description: 'Perform all migrations')]
  #[Usage(name: 'migrate:import --group=beer', description: 'Import all migrations in the beer group')]
  #[Usage(name: 'migrate:import --tag=user', description: 'Import all migrations with the user tag')]
  #[Usage(name: 'migrate:import --group=beer --tag=user', description: 'Import all migrations in the beer group and with the user tag')]
  #[Usage(name: 'migrate:import beer_term,beer_node', description: 'Import new terms and nodes')]
  #[Usage(name: 'migrate:import beer_user --limit=2', description: 'Import no more than 2 users')]
  #[Usage(name: 'migrate:import beer_user --idlist=5', description: 'Import the user record with source ID 5')]
  #[Usage(name: 'migrate:import beer_node_revision --idlist=1:2,2:3,3:5', description: 'Import the node revision record with source IDs [1,2], [2,3], and [3,5]')]
  #[Usage(name: 'migrate:import beer_user --limit=50 --feedback=20', description: 'Import 50 users and show process message every 20th record')]
  #[Usage(name: 'migrate:import --all --sync', description: 'Perform all migrations and delete the destination items that are missing from source')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function import(
    $migration_names = '',
    array $options = [
      'all' => FALSE,
      'group' => self::REQ,
      'tag' => self::REQ,
      'limit' => self::REQ,
      'feedback' => self::REQ,
      'idlist' => self::REQ,
      'idlist-delimiter' => MigrateTools::DEFAULT_ID_LIST_DELIMITER,
      'update' => FALSE,
      'force' => FALSE,
      'continue-on-failure' => FALSE,
      'execute-dependencies' => FALSE,
      'skip-progress-bar' => FALSE,
      'sync' => FALSE,
    ],
  ): void {
    $group_names = $options['group'];
    $tag_names = $options['tag'];
    $all = $options['all'];
    if (!$all && !$group_names && !$migration_names && !$tag_names) {
      throw new \Exception(dt('You must specify --all, --group, --tag or one or more migration names separated by commas'));
    }

    $migrations = $this->migrationsList($migration_names, $options);
    if (empty($migrations)) {
      $this->logger->error(dt('No migrations found.'));
    }

    // Take it one group at a time, importing the migrations within each group.
    foreach ($migrations as $migration_list) {
      // Don't execute disabled migrations.
      foreach ($migration_list as $migration_id => $migration) {
        if ($migration->getStatus() == MigrationInterface::STATUS_DISABLED) {
          continue;
        }
        $this->executeMigration($migration, $migration_id, $options);
      }
    }
  }

  /**
   * Rollback one or more migrations.
   *
   * @param string $migration_names
   *   Name of migration(s) to rollback. Delimit multiple using commas.
   * @param array $options
   *   Additional options for the command.
   *
   * @command migrate:rollback
   *
   * @option all Process all migrations.
   * @option group A comma-separated list of migration groups to rollback
   * @option tag ID of the migration tag to rollback
   * @option feedback Frequency of progress messages, in items processed
   * @option idlist Comma-separated list of IDs to rollback
   * @option idlist-delimiter The delimiter for records
   * @option skip-progress-bar Skip displaying a progress bar.
   * @option continue-on-failure When a rollback fails, continue processing
   *   remaining migrations.
   *
   * @default $options []
   *
   * @usage migrate:rollback --all
   *   Perform all migrations
   * @usage migrate:rollback --group=beer
   *   Rollback all migrations in the beer group
   * @usage migrate:rollback --tag=user
   *   Rollback all migrations with the user tag
   * @usage migrate:rollback --group=beer --tag=user
   *   Rollback all migrations in the beer group and with the user tag
   * @usage migrate:rollback beer_term,beer_node
   *   Rollback imported terms and nodes
   * @usage migrate:rollback beer_user --idlist=5
   *   Rollback imported user record with source ID 5
   * @validate-module-enabled migrate_tools
   *
   * @aliases mr, migrate-rollback
   *
   * @throws \Exception
   *   If there are not enough parameters to the command.
   */
  #[Command(name: 'migrate:rollback', aliases: ['mr', 'migrate-rollback'])]
  #[Argument(name: 'migration_names', description: 'Comma-separated list of migration IDs.')]
  #[Option(name: 'all', description: 'Process all migrations')]
  #[Option(name: 'group', description: 'A comma-separated list of migration groups to rollback')]
  #[Option(name: 'tag', description: 'ID of the migration tag to rollback')]
  #[Option(name: 'feedback', description: 'Frequency of progress messages, in items processed')]
  #[Option(name: 'idlist', description: 'Comma-separated list of IDs to rollback')]
  #[Option(name: 'idlist-delimiter', description: 'The delimiter for records')]
  #[Option(name: 'continue-on-failure', description: 'When a migration fails, continue processing remaining migrations.')]
  #[Option(name: 'skip-progress-bar', description: 'Skip displaying a progress bar.')]
  #[Usage(name: 'migrate:rollback --all', description: 'Rollback all migrations')]
  #[Usage(name: 'migrate:rollback --group=beer', description: 'Rollback all migrations in the beer group')]
  #[Usage(name: 'migrate:rollback --tag=user', description: 'Rollback all migrations tagged with user tag')]
  #[Usage(name: 'migrate:rollback --group=beer --tag=user', description: 'Rollback all migrations in the beer group and with the user tag')]
  #[Usage(name: 'migrate:rollback beer_term,beer_node', description: 'Rollback terms and nodes imported terms and nodes')]
  #[Usage(name: 'migrate:rollback beer_user --idlist=5', description: 'Rollback imported user record with source ID 5')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function rollback(
    $migration_names = '',
    array $options = [
      'all' => FALSE,
      'group' => self::REQ,
      'tag' => self::REQ,
      'feedback' => self::REQ,
      'idlist' => self::REQ,
      'idlist-delimiter' => MigrateTools::DEFAULT_ID_LIST_DELIMITER,
      'skip-progress-bar' => FALSE,
      'continue-on-failure' => FALSE,
    ],
  ): void {
    $group_names = $options['group'];
    $tag_names = $options['tag'];
    $all = $options['all'];
    if (!$all && !$group_names && !$migration_names && !$tag_names) {
      throw new \Exception(dt('You must specify --all, --group, --tag, or one or more migration names separated by commas'));
    }

    $migrations = $this->migrationsList($migration_names, $options);
    if (empty($migrations)) {
      $this->logger()->error(dt('No migrations found.'));
    }

    // Take it one group at a time,
    // rolling back the migrations within each group.
    $has_failure = FALSE;
    foreach ($migrations as $migration_list) {
      // Roll back in reverse order.
      $migration_list = array_reverse($migration_list);
      foreach ($migration_list as $migration_id => $migration) {
        // Skip disabled migrations.
        if ($migration->getStatus() == MigrationInterface::STATUS_DISABLED) {
          continue;
        }
        if ($options['skip-progress-bar']) {
          $migration->set('skipProgressBar', TRUE);
        }
        // Initialize the Symfony Console progress bar.
        $this->commandProgress->initializeProgress(
          $this->output(),
          $migration,
        );
        $executable = new MigrateExecutable(
          $migration,
          $this->getMigrateMessage(),
          $this->keyValue,
          $this->time,
          $this->translation,
          $options
        );
        // \drush_op() provides --simulate support.
        $result = \drush_op([$executable, 'rollback']);
        if ($result == MigrationInterface::RESULT_FAILED) {
          $has_failure = TRUE;
          $errored_migration_id = $migration_id;
        }
      }
    }

    // If any rollbacks failed, throw an exception to generate exit status.
    if ($has_failure) {
      $error_message = \dt('@name migration failed.', ['@name' => $errored_migration_id]);
      if ($options['continue-on-failure']) {
        $this->logger()->error($error_message);
      }
      else {
        // Nudge Drush to use a non-zero exit code.
        throw new \Exception($error_message);
      }
    }
  }

  /**
   * Stop an active migration operation.
   *
   * @param string $migration_id
   *   ID of migration to stop.
   *
   * @command migrate:stop
   *
   * @validate-module-enabled migrate_tools
   * @aliases mst, migrate-stop
   */
  #[Command(name: 'migrate:stop', aliases: ['mst', 'migrate-stop'])]
  #[Argument(name: 'migration_id', description: 'The ID of migration to stop.')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function stop($migration_id): void {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance(
      $migration_id
    );
    if ($migration) {
      $status = $migration->getStatus();
      switch ($status) {
        case MigrationInterface::STATUS_IDLE:
          $this->logger()->warning(
            \dt('Migration @id is idle', ['@id' => $migration_id])
          );
          break;

        case MigrationInterface::STATUS_DISABLED:
          $this->logger()->warning(
            \dt('Migration @id is disabled', ['@id' => $migration_id])
          );
          break;

        case MigrationInterface::STATUS_STOPPING:
          $this->logger()->warning(
            \dt('Migration @id is already stopping', ['@id' => $migration_id])
          );
          break;

        default:
          $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
          $this->logger()->notice(
            \dt('Migration @id requested to stop', ['@id' => $migration_id])
          );
          break;
      }
    }
    else {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger()->error($error);
      throw new \Exception($error);
    }
  }

  /**
   * Reset a active migration's status to idle.
   *
   * @param string $migration_id
   *   ID of migration to reset.
   *
   * @command migrate:reset-status
   *
   * @validate-module-enabled migrate_tools
   * @aliases mrs, migrate-reset-status
   */
  #[Command(
    name: 'migrate:reset-status',
    aliases: ['mrs', 'migrate-reset-status']
  )]
  #[Argument(name: 'migration_id', description: 'The ID of migration to reset.')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function resetStatus($migration_id = ''): void {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance(
      $migration_id
    );
    if ($migration) {
      $status = $migration->getStatus();
      if ($status == MigrationInterface::STATUS_IDLE) {
        $this->logger()->warning(
          \dt('Migration @id is already Idle', ['@id' => $migration_id])
        );
      }
      else {
        $migration->setStatus(MigrationInterface::STATUS_IDLE);
        $this->logger()->notice(
          \dt('Migration @id reset to Idle', ['@id' => $migration_id])
        );
      }
    }
    else {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger()->error($error);
      throw new \Exception($error);
    }
  }

  /**
   * Disable a migration.
   *
   * @param string $migration_id
   *   ID of migration to disable.
   *
   * @command migrate:disable
   *
   * @validate-module-enabled migrate_tools
   * @aliases mdis, migrate-disable
   */
  #[Command(name: 'migrate:disable', aliases: ['mdis', 'migrate-disable'])]
  #[Argument(name: 'migration_id', description: 'The ID of migration to disable.')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  public function disable($migration_id = ''): void {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance(
      $migration_id
    );
    if ($migration) {
      $status = $migration->getStatus();
      if ($status == MigrationInterface::STATUS_DISABLED) {
        $this->logger()->warning(
          \dt('Migration @id is already disabled', ['@id' => $migration_id])
        );
      }
      else {
        $migration->setStatus(MigrationInterface::STATUS_DISABLED);
        $this->logger()->notice(
          \dt('Migration @id disabled', ['@id' => $migration_id])
        );
      }
    }
    else {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger()->error($error);
      throw new \Exception($error);
    }
  }

  /**
   * View any messages associated with a migration.
   *
   * @param string $migration_id
   *   ID of the migration.
   * @param array $options
   *   Additional options for the command.
   *
   * @command migrate:messages
   *
   * @option csv Export messages as a CSV (deprecated)
   * @option idlist Comma-separated list of IDs to import
   * @option idlist-delimiter The delimiter for records
   *
   * @default $options []
   *
   * @usage migrate:messages MyNode
   *   Show all messages for the MyNode migration
   *
   * @validate-module-enabled migrate_tools
   *
   * @aliases mmsg,migrate-messages
   *
   * @field-labels
   *   source_ids_hash: Source IDs Hash
   *   source_ids: Source ID(s)
   *   destination_ids: Destination ID(s)
   *   level: Level
   *   message: Message
   * @default-fields source_ids_hash,source_ids,destination_ids,level,message
   *
   *   Source fields of the given migration formatted as a table.
   */
  #[Command(name: 'migrate:messages', aliases: ['mmsg', 'migrate-messages'])]
  #[Argument(name: 'migration_id', description: 'The ID of the migration.')]
  #[Option(name: 'csv', description: 'Export messages as a CSV (deprecated)')]
  #[Option(name: 'idlist', description: 'Comma-separated list of IDs to rollback')]
  #[Option(name: 'idlist-delimiter', description: 'The delimiter for records')]
  #[Usage(name: 'migrate:messages article', description: 'Show all messages for the <info>article</info> migration')]
  #[Usage(name: 'migrate:messages node_revision --idlist=1:2,2:3,3:5', description: 'Show messages related to node revision records with source IDs [1,2], [2,3], and [3,5].')]
  #[Usage(name: 'migrate:messages custom_node_revision --idlist=1:"r:1",2:"r:3"', description: 'Show messages related to node revision records with source IDs [1,"r:1"], and [2,"r:3"].')]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  // phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
  #[FieldLabels(labels: [
    'source_ids_hash' => 'Source IDs Hash',
    'source_ids' => 'Source ID(s)',
    'destination_ids' => 'Destination ID(s)',
    'level' => 'Level',
    'message' => 'Message',
  ])]
  // phpcs:enable
  #[DefaultFields(
    fields: [
      'source_ids_hash',
      'source_ids',
      'destination_ids',
      'level',
      'message',
    ]
  )]
  // phpcs:disable Drupal.Commenting.FunctionComment.Missing, Squiz.WhiteSpace.FunctionSpacing.Before
  public function messages(
    $migration_id,
    array $options = [
      'csv' => FALSE,
      'idlist' => self::REQ,
      'idlist-delimiter' => MigrateTools::DEFAULT_ID_LIST_DELIMITER,
    ],
  ): ?RowsOfFields {
  // phpcs:enable
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance(
      $migration_id
    );
    if (!$migration) {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger()->error($error);
      throw new \Exception($error);
    }
    $id_list = MigrateTools::buildIdList($options);
    /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface|\Drupal\migrate_tools\IdMapFilter $map */
    $map = new IdMapFilter($migration->getIdMap(), $id_list);
    $source_id_keys = $this->getSourceIdKeys($map);
    if ($source_id_keys === NULL) {
      $this->logger()->notice(dt('Migration has not yet run'));
      return NULL;
    }
    $table = [];

    $level_mapping = MigrateTools::getLogLevelLabelMapping();
    foreach ($map->getMessages() as $row) {
      unset($row->msgid);
      $array_row = (array) $row;
      // If the message includes useful IDs don't print the hash.
      if (count($source_id_keys) === count(array_intersect_key($source_id_keys, $array_row))) {
        unset($array_row['source_ids_hash']);
      }
      $source_ids = $destination_ids = [];
      foreach ($array_row as $name => $item) {
        if (substr($name, 0, 4) === 'src_') {
          $source_ids[$name] = $item;
        }
        if (substr($name, 0, 5) === 'dest_') {
          $destination_ids[$name] = $item;
        }
      }
      $array_row['level'] = $level_mapping[$array_row['level']];
      $array_row['source_ids'] = implode(MigrateTools::DEFAULT_ID_LIST_DELIMITER, $source_ids);
      $array_row['destination_ids'] = array_filter($destination_ids) ? implode(MigrateTools::DEFAULT_ID_LIST_DELIMITER, $destination_ids) : '';
      $table[] = $array_row;
    }
    if (empty($table)) {
      $this->logger()->notice(dt('No messages for this migration'));
      return NULL;
    }

    if ($options['csv']) {
      fputcsv(STDOUT, array_keys($table[0]));
      foreach ($table as $row) {
        fputcsv(STDOUT, $row);
      }
      $this->logger()->notice('--csv option is deprecated in 4.5 and is removed from 5.0. Use \'--format=csv\' instead.');
      @trigger_error('--csv option is deprecated in migrate_tool:8.x-4.5 and is removed from migrate_tool:8.x-5.0. Use \'--format=csv\' instead. See https://www.drupal.org/node/123', E_USER_DEPRECATED);
      return NULL;
    }
    return new RowsOfFields($table);
  }

  /**
   * Get the source ID keys.
   *
   * @param \Drupal\migrate_tools\IdMapFilter $map
   *   The migration ID map.
   *
   *   The source ID keys.
   */
  protected function getSourceIdKeys(IdMapFilter $map): array {
    $map->rewind();
    $columns = $map->currentSource();
    if ($columns === NULL) {
      return $columns;
    }
    $source_id_keys = array_map(static fn($id): string => 'src_' . $id, array_keys($columns));
    return array_combine($source_id_keys, $source_id_keys);
  }

  /**
  * List the fields available for mapping in a source.
  *
  * @param string $migration_id
  *   ID of the migration.
  *
  * @command migrate:fields-source
  *
  * @usage migrate:fields-source my_node
  *   List fields for the source in the my_node migration.
  *
  * @validate-module-enabled migrate_tools
  *
  * @aliases mfs, migrate-fields-source
   *
  * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
  *   Source fields of the given migration formatted as a table.
  */
  #[Command(
    name: 'migrate:fields-source',
    aliases: ['mfs', 'migrate-fields-source']
  )]
  #[Argument(
    name: 'migration_id',
    description: 'The ID of the migration.'
  )]
  #[Topics(topics: ['migrate'])]
  #[ValidateModulesEnabled(modules: ['migrate_tools'])]
  #[FieldLabels(
    labels: [
      'machine_name' => 'Machine Name',
      'description' => 'Description',
    ]
  )]
  #[DefaultFields(fields: ['machine_name', 'description'])]
  public function fieldsSource($migration_id): RowsOfFields {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance(
      $migration_id
    );
    if ($migration) {
      $source = $migration->getSourcePlugin();
      $table = [];
      foreach ($source->fields() as $machine_name => $description) {
        $table[] = [
          'machine_name' => $machine_name,
          'description' => strip_tags((string) $description),
        ];
      }
      return new RowsOfFields($table);
    }
    else {
      $error = \dt('Migration @id does not exist', ['@id' => $migration_id]);
      $this->logger()->error($error);
      throw new \Exception($error);
    }
  }

  /**
   * Retrieve a list of active migrations.
   *
   * @param string $migration_ids
   *   Comma-separated list of migrations -
   *   if present, return only these migrations.
   * @param array $options
   *   Command options.
   *
   * @default $options []
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface[][]
   *   An array keyed by migration group, each value containing an array of
   *   migrations or an empty array if no migrations match the input criteria.
   */
  protected function migrationsList($migration_ids = '', array $options = []): array {
    $filter = [];
    // Filter keys must match the migration configuration property name.
    $filter['migration_group'] = $filter['migration_tags'] = [];
    if (!empty($options['group'])) {
      $filter['migration_group'] = explode(',', (string) $options['group']);
    }
    if (!empty($options['tag'])) {
      $filter['migration_tags'] = explode(',', (string) $options['tag']);
    }

    $manager = $this->migrationPluginManager;

    $matched_migration_definitions = $manager->getDefinitions();

    // Filter by specific migration IDs.
    if ($migration_ids) {
      $filtered_migrations = [];

      // Get the requested migrations.
      $migration_ids = explode(',', mb_strtolower($migration_ids));

      foreach ($migration_ids as $given_migration_id) {
        if (isset($matched_migration_definitions[$given_migration_id])) {
          $filtered_migrations[$given_migration_id] = $matched_migration_definitions[$given_migration_id];
        }
        else {
          $error_message = \dt('Migration @id does not exist', ['@id' => $given_migration_id]);
          if ($options['continue-on-failure']) {
            $this->logger()->error($error_message);
          }
          else {
            throw new \Exception($error_message);
          }
        }
      }

      $matched_migration_definitions = $filtered_migrations;
    }

    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        if (!empty($values)) {
          $filtered_migrations = [];
          foreach ($values as $search_value) {
            foreach ($matched_migration_definitions as $id => $migration_definition) {
              // Cast to array because migration_tags can be an array.
              $configured_values = (array) ($migration_definition[$property] ?? NULL);
              $configured_id = in_array($search_value, $configured_values, TRUE) ? $search_value : 'default';
              if (empty($search_value) || $search_value === $configured_id) {
                if (empty($migration_ids) || in_array(
                    mb_strtolower($id),
                    $migration_ids,
                    TRUE
                  )) {
                  $filtered_migrations[$id] = $migration_definition;
                }
              }
            }
          }
          $matched_migration_definitions = $filtered_migrations;
        }
      }
    }

    $matched_migrations = [];
    foreach (array_keys($matched_migration_definitions) as $id) {
      $matched_migrations[$id] = $manager->createInstance($id);
    }

    // Do not return any migrations which fail to meet requirements.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      try {
        if ($migration->getSourcePlugin() instanceof RequirementsInterface) {
          $migration->getSourcePlugin()->checkRequirements();
        }
      }
      catch (RequirementsException) {
        unset($matched_migrations[$id]);
      }
      catch (PluginNotFoundException $exception) {
        if ($options['continue-on-failure']) {
          $this->logger()->error($exception->getMessage());
          unset($matched_migrations[$id]);
        }
        else {
          throw $exception;
        }
      }
    }

    // Sort the matched migrations by group.
    if (!empty($matched_migrations)) {
      foreach ($matched_migrations as $id => $migration) {
        $configured_group_id = empty($migration->migration_group) ? 'default' : $migration->migration_group;
        $migrations[$configured_group_id][$id] = $migration;
      }
    }
    return $migrations ?? [];
  }

  /**
   * Executes a single migration.
   *
   * If the --execute-dependencies option was given,
   * the migration's dependencies will also be executed first.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration to execute.
   * @param string $migration_id
   *   The migration ID (not used, just an artifact of array_walk()).
   * @param array $options
   *   Additional options of the command.
   *
   * @default $options []
   *
   * @throws \Exception
   *   If some migrations failed during execution.
   */
  protected function executeMigration(MigrationInterface $migration, $migration_id, array $options = []): void {
    // Keep track of all migrations run during this command so the same
    // migration is not run multiple times.
    static $executed_migrations = [];

    if ($options['execute-dependencies']) {
      $required_migrations = $migration->getRequirements();
      $required_migrations = array_filter($required_migrations, static fn($value): bool => !isset($executed_migrations[$value]));

      if (!empty($required_migrations)) {
        $manager = $this->migrationPluginManager;
        $required_migrations = $manager->createInstances($required_migrations);
        $dependency_options = array_merge($options, ['is_dependency' => TRUE]);
        array_walk($required_migrations, [$this, __FUNCTION__], $dependency_options);
        $executed_migrations += $required_migrations;
      }
    }
    if ($options['sync']) {
      $migration->set('syncSource', TRUE);
    }
    if ($options['skip-progress-bar']) {
      $migration->set('skipProgressBar', TRUE);
    }
    if ($options['continue-on-failure']) {
      $migration->set('continueOnFailure', TRUE);
    }
    if ($options['force']) {
      $migration->set('requirements', []);
    }
    if ($options['update']) {
      if (!$options['idlist']) {
        $migration->getIdMap()->prepareUpdate();
      }
      else {
        $source_id_values_list = MigrateTools::buildIdList($options);
        $keys = array_keys($migration->getSourcePlugin()->getIds());
        foreach ($source_id_values_list as $source_id_values) {
          $migration->getIdMap()->setUpdate(array_combine($keys, $source_id_values));
        }
      }
    }

    // Initialize the Symfony Console progress bar.
    $this->commandProgress->initializeProgress(
      $this->output(),
      $migration,
      $options
    );

    $executable = new MigrateExecutable(
      $migration,
      $this->getMigrateMessage(),
      $this->keyValue,
      $this->time,
      $this->translation,
      $options,
    );
    // \drush_op() provides --simulate support.
    $result = \drush_op([$executable, 'import']);
    $executed_migrations += [$migration_id => $migration_id];
    if ($count = $executable->getFailedCount()) {
      $error_message = \dt(
        '@name Migration - @count failed.',
        ['@name' => $migration_id, '@count' => $count]
      );
    }
    elseif ($result == MigrationInterface::RESULT_FAILED) {
      $error_message = \dt('@name migration failed.', ['@name' => $migration_id]);
    }
    else {
      $error_message = '';
    }
    if ($error_message) {
      if ($options['continue-on-failure']) {
        $this->logger()->error($error_message);
      }
      else {
        // Nudge Drush to use a non-zero exit code.
        throw new \Exception($error_message);
      }
    }
  }

  /**
   * Gets the migrate message logger.
   *
   *   The migrate message service.
   */
  protected function getMigrateMessage(): DrushLogMigrateMessage {
    if (!isset($this->migrateMessage)) {
      $this->migrateMessage = new DrushLogMigrateMessage($this->logger());
    }
    return $this->migrateMessage;
  }

}
