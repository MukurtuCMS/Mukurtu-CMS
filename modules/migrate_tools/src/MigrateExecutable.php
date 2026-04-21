<?php

declare(strict_types=1);

namespace Drupal\migrate_tools;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateExecutable as MigrateExecutableBase;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Event\MigrateEvents as MigratePlusEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

/**
 * Defines a migrate executable class for drush.
 */
class MigrateExecutable extends MigrateExecutableBase {

  /**
   * Counters of map statuses.
   *
   *   Set of counters, keyed by MigrateIdMapInterface::STATUS_* constant.
   */
  protected array $saveCounters = [
    MigrateIdMapInterface::STATUS_FAILED => 0,
    MigrateIdMapInterface::STATUS_IGNORED => 0,
    MigrateIdMapInterface::STATUS_IMPORTED => 0,
    MigrateIdMapInterface::STATUS_NEEDS_UPDATE => 0,
  ];

  /**
   * Counter of map saves, used to detect the item limit threshold.
   *
   * @var int
   */
  protected $itemLimitCounter = 0;

  /**
   * Counter of map deletions.
   */
  protected int $deleteCounter = 0;

  /**
   * Maximum number of items to process in this migration.
   *
   * 0 indicates no limit is to be applied.
   *
   * @var int
   */
  protected $itemLimit = 0;

  /**
   * Frequency (in items) at which progress messages should be emitted.
   *
   * @var int
   */
  protected $feedback = 0;

  /**
   * List of specific source IDs to import.
   */
  protected array $idlist = [];

  /**
   * Count of number of items processed so far in this migration.
   *
   * @var int
   */
  protected $counter = 0;

  /**
   * Whether the destination item exists before saving.
   *
   * @var bool
   */
  protected bool $preExistingItem = FALSE;

  /**
   * List of event listeners we have registered.
   *
   * @var array
   */
  protected $listeners = [];

  /**
   * The key/value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValue;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|mixed
   */
  protected TimeInterface $time;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected TranslationInterface $translation;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MigrationInterface $migration,
    ?MigrateMessageInterface $message = NULL,
    KeyValueFactoryInterface|array $keyValue = [],
    ?TimeInterface $time = NULL,
    ?TranslationInterface $translation = NULL,
    array $options = [],
  ) {
    if (!$message instanceof MigrateMessageInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $message argument is deprecated in migrate_tools:6.1.0 and it will be required in migrate_tools:7.0.0. See https://www.drupal.org/node/3537201', E_USER_DEPRECATED);
      $message = new MigrateMessage();
    }
    if (!$keyValue instanceof KeyValueFactoryInterface) {
      // If options aren't passed, the keyValue parameter must be the options.
      if (!isset($options) || empty($options)) {
        $options = $keyValue;
      }
      @trigger_error('Calling ' . __METHOD__ . '() without the $keyValue argument is deprecated in migrate_tools:6.1.0 and it will be required in migrate_tools:7.0.0. See https://www.drupal.org/node/3537201', E_USER_DEPRECATED);
      $keyValue = \Drupal::service('keyvalue');
    }
    if (!$time instanceof TimeInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $time argument is deprecated in migrate_tools:6.1.0 and it will be required in migrate_tools:7.0.0. See https://www.drupal.org/node/3537201', E_USER_DEPRECATED);
      $time = \Drupal::service('datetime.time');
    }
    if (!$translation instanceof TranslationInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $translation argument is deprecated in migrate_tools:6.1.0 and it will be required in migrate_tools:7.0.0. See https://www.drupal.org/node/3537201', E_USER_DEPRECATED);
      $translation = \Drupal::service('string_translation');
    }
    $this->keyValue = $keyValue;
    $this->time = $time;
    $this->translation = $translation;
    parent::__construct($migration, $message);
    if (isset($options['limit'])) {
      $this->itemLimit = $options['limit'];
    }
    if (isset($options['feedback'])) {
      $this->feedback = $options['feedback'];
    }
    if (isset($options['sync'])) {
      $this->migration->set('syncSource', $options['sync']);
    }
    $this->idlist = MigrateTools::buildIdList($options);

    $this->listeners[MigrateEvents::MAP_SAVE] = [
      $this,
      'onMapSave',
    ];
    $this->listeners[MigrateEvents::MAP_DELETE] = [
      $this,
      'onMapDelete',
    ];
    $this->listeners[MigrateEvents::POST_IMPORT] = [
      $this,
      'onPostImport',
    ];
    $this->listeners[MigrateEvents::POST_ROLLBACK] = [
      $this,
      'onPostRollback',
    ];
    $this->listeners[MigrateEvents::PRE_ROW_SAVE] = [
      $this,
      'onPreRowSave',
    ];
    $this->listeners[MigrateEvents::POST_ROW_DELETE] = [
      $this,
      'onPostRowDelete',
    ];
    if (class_exists(MigratePlusEvents::class)) {
      $this->listeners[MigratePlusEvents::PREPARE_ROW] = [
        $this,
        'onPrepareRow',
      ];
    }
    foreach ($this->listeners as $event => $listener) {
      $this->resetListeners($event);
      $this->getEventDispatcher()->addListener($event, $listener);
    }
  }

  /**
   * Count up any map save events.
   *
   * @param \Drupal\migrate\Event\MigrateMapSaveEvent $event
   *   The map event.
   */
  public function onMapSave(MigrateMapSaveEvent $event) {
    // Only count saves for this migration.
    if ($event->getMap()->getQualifiedMapTableName() == $this->migration->getIdMap()->getQualifiedMapTableName()) {
      $fields = $event->getFields();
      $this->itemLimitCounter++;
      // Distinguish between creation and update.
      if ($fields['source_row_status'] == MigrateIdMapInterface::STATUS_IMPORTED &&
        $this->preExistingItem
      ) {
        $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE]++;
      }
      else {
        $this->saveCounters[$fields['source_row_status']]++;
      }
    }
  }

  /**
   * Count up any rollback events.
   *
   * @param \Drupal\migrate\Event\MigrateMapDeleteEvent $event
   *   The map event.
   */
  public function onMapDelete(MigrateMapDeleteEvent $event) {
    $this->deleteCounter++;
  }

  /**
   * Return the number of items created.
   *
   * @return int
   *   The number of items created.
   */
  public function getCreatedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED];
  }

  /**
   * Return the number of items updated.
   *
   * @return int
   *   The updated count.
   */
  public function getUpdatedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE];
  }

  /**
   * Return the number of items ignored.
   *
   * @return int
   *   The ignored count.
   */
  public function getIgnoredCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED];
  }

  /**
   * Return the number of items that failed.
   *
   * @return int
   *   The failed count.
   */
  public function getFailedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
  }

  /**
   * Return the total number of items processed.
   *
   * Note that STATUS_NEEDS_UPDATE is not counted, since this is typically set
   * on stubs created as side effects, not on the primary item being imported.
   *
   * @return int
   *   The processed count.
   */
  public function getProcessedCount() {
    return $this->saveCounters[MigrateIdMapInterface::STATUS_IMPORTED] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_NEEDS_UPDATE] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_IGNORED] +
      $this->saveCounters[MigrateIdMapInterface::STATUS_FAILED];
  }

  /**
   * Return the number of items rolled back.
   *
   * @return int
   *   The rollback count.
   */
  public function getRollbackCount() {
    return $this->deleteCounter;
  }

  /**
   * Reset all the per-status counters to 0.
   */
  protected function resetCounters() {
    foreach ($this->saveCounters as $status => $count) {
      $this->saveCounters[$status] = 0;
    }
    $this->deleteCounter = 0;
  }

  /**
   * React to migration completion.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The map event.
   */
  public function onPostImport(MigrateImportEvent $event) {
    $migrate_last_imported_store = $this->keyValue->get('migrate_last_imported');
    $migrate_last_imported_store->set($event->getMigration()->id(), round($this->time->getCurrentMicroTime() * 1000));
    $this->progressMessage();
    $this->removeListeners();

    $keys = array_keys($this->getSource()->getIds());
    $unused_ids = $this->getSource()->getRemainingIdList();
    $map_rows = [];
    $bad_rows = [];
    foreach ($unused_ids as $unused_id) {
      if (count($keys) == count($unused_id)) {
        $selector = array_combine($keys, $unused_id);
        // Handle possible TypeError.
        // @see https://www.drupal.org/project/migrate_tools/issues/3416010
        try {
          $row_data = $this->getIdMap()->getRowBySource($selector);
          $map_rows[implode(':', $unused_id)] = $row_data;
        }
        catch (\TypeError $e) {
          $bad_rows[] = implode(':', $unused_id);
        }
      }
      else {
        $id = implode(':', $unused_id);
        $this->message->display($this->t("Invalid source id: Source id @id does not match this migration's source key count, which is :count.", [
          '@id' => $id,
          ':count' => count($keys),
        ]));
        $bad_rows[] = $id;
      }
    }
    if ($bad_rows) {
      $this->message->display($this->t("The following specified IDs could not be migrated: @idlist. They may be missing from your source.", [
        '@idlist' => implode(', ', $bad_rows),
      ]));
    }
    if ($map_rows) {
      $this->message->display($this->t("The following specified IDs have been migrated in the past, but were ignored in this migration: @idlist. Check the migrate status of their rows for more information.", [
        '@idlist' => implode(', ', array_keys($map_rows)),
      ]));
    }
  }

  /**
   * Clean up all our event listeners.
   */
  protected function removeListeners() {
    foreach ($this->listeners as $event => $listener) {
      // Don't remove the listener for the events that are currently being
      // dispatched.
      if ($event !== MigrateEvents::POST_IMPORT && $event !== MigrateEvents::POST_ROLLBACK) {
        $this->getEventDispatcher()->removeListener($event, $listener);
      }
    }
  }

  /**
   * Clean up the event listeners that cannot be removed by removeListeners().
   *
   * @param string $event_name
   *   The name of the event to remove.
   */
  protected function resetListeners(string $event_name) {
    if (in_array($event_name, [
      MigrateEvents::POST_IMPORT,
      MigrateEvents::POST_ROLLBACK,
    ], TRUE)) {
      foreach ($this->getEventDispatcher()->getListeners($event_name) as $registered_listener) {
        if ($registered_listener[0] instanceof self) {
          $this->getEventDispatcher()->removeListener($event_name, $registered_listener);
        }
      }
    }
  }

  /**
   * Emit information on what we've done.
   *
   * Either since the last feedback or the beginning of this migration.
   *
   * @param bool $done
   *   TRUE if this is the last items to process. Otherwise FALSE.
   */
  protected function progressMessage($done = TRUE) {
    $processed = $this->getProcessedCount();
    if ($done) {
      $singular_message = "Processed 1 item (@created created, @updated updated, @failures failed, @ignored ignored) - done with '@name'";
      $plural_message = "Processed @numItems items (@created created, @updated updated, @failures failed, @ignored ignored) - done with '@name'";
    }
    else {
      $singular_message = "Processed 1 item (@created created, @updated updated, @failures failed, @ignored ignored) - continuing with '@name'";
      $plural_message = "Processed @numItems items (@created created, @updated updated, @failures failed, @ignored ignored) - continuing with '@name'";
    }
    $this->message->display($this->translation->formatPlural($processed,
      $singular_message, $plural_message,
        [
          '@numItems' => $processed,
          '@created' => $this->getCreatedCount(),
          '@updated' => $this->getUpdatedCount(),
          '@failures' => $this->getFailedCount(),
          '@ignored' => $this->getIgnoredCount(),
          '@name' => $this->migration->id(),
        ]
    ));
  }

  /**
   * React to rollback completion.
   *
   * @param \Drupal\migrate\Event\MigrateRollbackEvent $event
   *   The map event.
   */
  public function onPostRollback(MigrateRollbackEvent $event) {
    $migrate_last_imported_store = $this->keyValue->get('migrate_last_imported');
    $migrate_last_imported_store->set($event->getMigration()->id(), FALSE);
    $this->rollbackMessage();
    // If this is a sync import, then don't remove listeners or post import will
    // not be executed. Leave it to post import to remove listeners.
    if (empty($event->getMigration()->syncSource)) {
      $this->removeListeners();
    }
  }

  /**
   * Emit information on what we've done.
   *
   * Either since the last feedback or the beginning of this migration.
   *
   * @param bool $done
   *   TRUE if this is the last items to rollback. Otherwise FALSE.
   */
  protected function rollbackMessage($done = TRUE) {
    if (($rolled_back = $this->getRollbackCount()) === 0) {
      $this->message->display($this->translation->translate(
        "No item has been rolled back - done with '@name'",
        ['@name' => $this->migration->id()])
      );
      return;
    }
    if ($done) {
      $singular_message = "Rolled back 1 item - done with '@name'";
      $plural_message = "Rolled back @numItems items - done with '@name'";
    }
    else {
      $singular_message = "Rolled back 1 item - continuing with '@name'";
      $plural_message = "Rolled back @numItems items - continuing with '@name'";
    }
    $this->message->display($this->translation->formatPlural($rolled_back,
      $singular_message, $plural_message,
      [
        '@numItems' => $rolled_back,
        '@name' => $this->migration->id(),
      ]
    ));
  }

  /**
   * React to an item about to be imported.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The pre-save event.
   */
  public function onPreRowSave(MigratePreRowSaveEvent $event) {
    $id_map = $event->getRow()->getIdMap();
    if (!empty($id_map['destid1'])) {
      $this->preExistingItem = TRUE;
    }
    else {
      $this->preExistingItem = FALSE;
    }
  }

  /**
   * React to item rollback.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The post-save event.
   */
  public function onPostRowDelete(MigrateRowDeleteEvent $event) {
    if ($this->feedback && ($this->deleteCounter) && $this->deleteCounter % $this->feedback == 0) {
      $this->rollbackMessage(FALSE);
      $this->resetCounters();
    }
  }

  /**
   * React to a new row.
   *
   * @param \Drupal\migrate_plus\Event\MigratePrepareRowEvent $event
   *   The prepare-row event.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    if ($this->feedback && $this->counter && $this->counter % $this->feedback == 0) {
      $this->progressMessage(FALSE);
      $this->resetCounters();
    }
    $this->counter++;
    if ($this->itemLimit && ($this->itemLimitCounter + 1) >= $this->itemLimit) {
      $event->getMigration()->interruptMigration(MigrationInterface::RESULT_COMPLETED);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getSource() {
    if (!isset($this->source)) {
      // Re-set $this->source which the call to the parent will have set.
      $this->source = new SourceFilter(parent::getSource(), $this->idlist);
    }

    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  protected function getIdMap(): IdMapFilter {
    return new IdMapFilter(parent::getIdMap(), $this->idlist);
  }

}
