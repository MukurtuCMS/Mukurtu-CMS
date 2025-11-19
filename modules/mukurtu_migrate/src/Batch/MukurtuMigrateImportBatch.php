<?php

namespace Drupal\mukurtu_migrate\Batch;

use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate_drupal_ui\Batch\MigrateMessageCapture;
use Drupal\migrate_drupal_ui\Batch\MigrateUpgradeImportBatch;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;

class MukurtuMigrateImportBatch extends MigrateUpgradeImportBatch {

  /**
   * {@inheritdoc}
   */
  public static function run($initial_ids, $config, &$context) {
    // Turn off transliteration to preserve diacritics in urls.
    \Drupal::service('config.factory')
      ->getEditable('pathauto.settings')
      ->set('transliterate', 0)
      ->save();
    /**
     * We've basically copied MigrateUpgradeImportBatch::run completely here.
     * We need some Mukurtu specific logging that we couldn't easily inject
     * otherwise.
     */

    if (!static::$listenersAdded) {
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->addListener(MigrateEvents::POST_ROW_SAVE, [static::class, 'onPostRowSave']);
      $event_dispatcher->addListener(MigrateEvents::POST_IMPORT, [static::class, 'onPostImport']);
      $event_dispatcher->addListener(MigrateEvents::MAP_SAVE, [static::class, 'onMapSave']);
      $event_dispatcher->addListener(MigrateEvents::IDMAP_MESSAGE, [static::class, 'onIdMapMessage']);

      static::$maxExecTime = ini_get('max_execution_time');
      if (static::$maxExecTime <= 0) {
        static::$maxExecTime = 60;
      }
      // Set an arbitrary threshold of 3 seconds (e.g., if max_execution_time is
      // 45 seconds, we will quit at 42 seconds so a slow item or cleanup
      // overhead don't put us over 45).
      static::$maxExecTime -= 3;
      static::$listenersAdded = TRUE;
    }
    if (!isset($context['sandbox']['migration_ids'])) {
      $context['sandbox']['max'] = count($initial_ids);
      $context['sandbox']['current'] = 1;
      // Total number processed for this migration.
      $context['sandbox']['num_processed'] = 0;
      // migration_ids will be the list of IDs remaining to run.
      $context['sandbox']['migration_ids'] = $initial_ids;
      $context['sandbox']['messages'] = [];
      $context['results']['failures'] = 0;
      $context['results']['successes'] = 0;
    }

    // Number processed in this batch.
    static::$numProcessed = 0;

    $migration_id = reset($context['sandbox']['migration_ids']);
    $definition = \Drupal::service('plugin.manager.migration')->getDefinition($migration_id);
    $configuration = [];

    // Set the source plugin constant, source_base_path, for all migrations with
    // a file entity destination.
    // @todo https://www.drupal.org/node/2804611.
    //   Find a way to avoid having to set configuration here.
    if ($definition['destination']['plugin'] === 'entity:file') {
      // Use the private file path if the scheme property is set in the source
      // plugin definition and is 'private' otherwise use the public file path.
      $scheme = $definition['source']['scheme'] ?? NULL;
      $base_path = ($scheme === 'private' && $config['source_private_file_path'])
        ? $config['source_private_file_path']
        : $config['source_base_path'];
      $configuration['source']['constants']['source_base_path'] = rtrim($base_path, '/');
    }

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id, $configuration);

    if ($migration) {
      static::$messages = new MigrateMessageCapture();
      $executable = new MigrateExecutable($migration, static::$messages);

      $migration_name = $migration->label() ? $migration->label() : $migration_id;

      try {
        $migration_status = $executable->import();
      }
      catch (\Exception $e) {
        \Drupal::logger('mukurtu_migrate')->error($e->getMessage());
        $migration_status = MigrationInterface::RESULT_FAILED;
      }

      switch ($migration_status) {
        case MigrationInterface::RESULT_COMPLETED:
          // Store the number processed in the sandbox.
          $context['sandbox']['num_processed'] += static::$numProcessed;
          $message = new PluralTranslatableMarkup(
            $context['sandbox']['num_processed'], 'Migration task "@migration" completed: 1 item', 'Migration task "@migration" completed: @count items',
            ['@migration' => $migration_name]);
          $context['sandbox']['messages'][] = (string) $message;
          \Drupal::logger('mukurtu_migrate')->notice($message);
          $context['sandbox']['num_processed'] = 0;
          $context['results']['successes']++;

          // If the completed migration has any follow-up migrations, add them
          // to the batch migrations.
          // @see onPostImport()
          if (!empty(static::$followUpMigrations)) {
            foreach (static::$followUpMigrations as $migration_id => $migration) {
              if (!in_array($migration_id, $context['sandbox']['migration_ids'], TRUE)) {
                // Add the follow-up migration ID to the batch migration IDs for
                // later execution.
                $context['sandbox']['migration_ids'][] = $migration_id;
                // Increase the number of migrations in the batch to update the
                // progress bar and keep it accurate.
                $context['sandbox']['max']++;
                // Unset the follow-up migration to make sure it won't get added
                // to the batch twice.
                unset(static::$followUpMigrations[$migration_id]);
              }
            }
          }
          break;

        case MigrationInterface::RESULT_INCOMPLETE:
          $context['sandbox']['messages'][] = (string) new PluralTranslatableMarkup(
            static::$numProcessed, 'Continuing with migration task "@migration" (processed 1 item)', 'Continuing with migration task "@migration" (processed @count items)',
            ['@migration' => $migration_name]);
          $context['sandbox']['num_processed'] += static::$numProcessed;
          break;

        case MigrationInterface::RESULT_STOPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation stopped by request');
          break;

        case MigrationInterface::RESULT_FAILED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Migration task "@migration" failed', ['@migration' => $migration_name]);
          $context['results']['failures']++;
          \Drupal::logger('mukurtu_migrate')->error('Migration task "@migration" failed', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_SKIPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Migration task "@migration" skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          \Drupal::logger('mukurtu_migrate')->error('Migration task "@migration" skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_DISABLED:
          // Skip silently if disabled.
          break;
      }

      // Unless we're continuing on with this migration, take it off the list.
      if ($migration_status != MigrationInterface::RESULT_INCOMPLETE) {
        array_shift($context['sandbox']['migration_ids']);
        $context['sandbox']['current']++;
      }

      // Add and log any captured messages.
      foreach (static::$messages->getMessages() as $message) {
        $context['sandbox']['messages'][] = (string) $message;
        \Drupal::logger('mukurtu_migrate')->error($message);
      }

      // Only display the last MESSAGE_LENGTH messages, in reverse order.
      $message_count = count($context['sandbox']['messages']);
      $context['message'] = '';
      for ($index = max(0, $message_count - self::MESSAGE_LENGTH); $index < $message_count; $index++) {
        $context['message'] = $context['sandbox']['messages'][$index] . "<br />\n" . $context['message'];
      }
      if ($message_count > self::MESSAGE_LENGTH) {
        // Indicate there are earlier messages not displayed.
        $context['message'] .= '&hellip;';
      }
      // At the top of the list, display the next one (which will be the one
      // that is running while this message is visible).
      if (!empty($context['sandbox']['migration_ids'])) {
        $migration_id = reset($context['sandbox']['migration_ids']);
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
        $migration_name = $migration->label() ? $migration->label() : $migration_id;
        $context['message'] = (string) new TranslatableMarkup('Currently running migration task "@migration" (@current of @max total tasks)', [
          '@migration' => $migration_name,
          '@current' => $context['sandbox']['current'],
          '@max' => $context['sandbox']['max'],
        ]) . "<br />\n" . $context['message'];
      }
    }
    else {
      array_shift($context['sandbox']['migration_ids']);
      $context['sandbox']['current']++;
    }

    $context['finished'] = 1 - count($context['sandbox']['migration_ids']) / $context['sandbox']['max'];
  }

  /**
   * Callback executed when the Mukurtu migrate batch process completes.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of methods run in the batch.
   * @param string $elapsed
   *   The time to run the batch.
   */
  public static function finished($success, $results, $operations, $elapsed) {
    $successes = $results['successes'];
    $failures = $results['failures'];

    if ($successes > 0) {
      \Drupal::messenger()->addStatus(\Drupal::translation()
        ->formatPlural($successes, 'Completed 1 migration task successfully', 'Completed @count migration tasks successfully'));
    }
    if ($failures > 0) {
      \Drupal::messenger()->addError(\Drupal::translation()
        ->formatPlural($failures, '1 migration failed', '@count migrations failed'));
      \Drupal::messenger()->addError(t('Migration process not completed'));
    }
    // Turn transliteration setting back on.
    \Drupal::service('config.factory')
      ->getEditable('pathauto.settings')
      ->set('transliterate', 1)
      ->save();

    // Create landing page if requested, otherwise set the Drupal default.
    // Without a landing page set, the Mukurtu install profile default may
    // be set to an arbitraty migrated node.
    $store = \Drupal::service('tempstore.private')->get('mukurtu_migrate');
    $create_landing_page = $store->get('create_landing_page');
    if ($success && $failures == 0) {
      if ($create_landing_page) {
        try {
          $landing_page_service = \Drupal::service('mukurtu_landing_page.default_landing_page');
          $homepage_node = $landing_page_service->createDefaultLandingPage();
          if ($homepage_node) {
            \Drupal::messenger()
              ->addStatus(t('Default landing page created and set as homepage.'));
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('mukurtu_migrate')
            ->error('Failed to create landing page: @message', ['@message' => $e->getMessage()]);
          \Drupal::messenger()
            ->addWarning(t('Failed to create default landing page.'));
        }
      }
      else {
        // Set the homepage to the Drupal default, so its set to something,
        // preventing errors.
        \Drupal::service('config.factory')
          ->getEditable('system.site')
          ->set('page.front', '/node')
          ->save();
        \Drupal::messenger()
          ->addStatus(t('The front page was set to the Drupal default of "/node". You will likely want to update this by navigating to <a href="/admin/config/system/site-information">Configuration > System > Basic Site Settings</a>.'));
      }
    }
  }

}
