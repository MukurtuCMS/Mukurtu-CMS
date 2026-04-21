<?php

namespace Drupal\search_api\Task;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a service for managing pending tasks.
 *
 * Tasks are executed by this service by dispatching an event with the class
 * \Drupal\search_api\Task\TaskEvent and the name "search_api.task.TYPE", where
 * TYPE is the type of task. Any module wishing to employ the Search API task
 * system can therefore just create events of any type they want as long as they
 * have a subscriber listening to events with the corresponding name.
 *
 * Contrib modules should, however, always prefix TYPE with their module short
 * name, followed by a period, to avoid collisions.
 *
 * The system is used by the Search API module itself in the following ways:
 * - Keeping track of failed method calls on search servers (or, rather, their
 *   backends). See \Drupal\search_api\Task\ServerTaskManager.
 * - Moving the adding of items to an index's tracker to a batch operation when
 *   a new index is created or a new datasource enabled for an index. See
 *   \Drupal\search_api\Task\IndexTaskManager.
 * - For content entity datasources, to similarly add/remove items to/from
 *   tracking when a datasource's configuration changes. See
 *   \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTaskManager.
 *   (Since this implements functionality for just one plugin, and not for the
 *   Search API in general, it uses the proper "search_api." prefix for the task
 *   type. Also, it should not be considered part of the framework.)
 *
 * @see \Drupal\search_api\Task\TaskEvent
 */
class TaskManager implements TaskManagerInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Whether a task is currently being executed.
   *
   * This is used to prevent nested execution of tasks: We never want to pause
   * execution of one task to execute others. This mess up the proper order in
   * which the tasks should be executed, and even lead to infinite loops.
   */
  protected static bool $hasActiveTask = FALSE;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventDispatcherInterface $eventDispatcher,
    TranslationInterface $translation,
    protected MessengerInterface $messenger,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Returns the entity storage for search tasks.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage handler.
   */
  protected function getTaskStorage() {
    return $this->entityTypeManager->getStorage('search_api_task');
  }

  /**
   * Creates an entity query matching the given search tasks.
   *
   * @param array $conditions
   *   (optional) An array of conditions to be matched for the tasks, with
   *   property names keyed to the value (or values, for multiple possibilities)
   *   that the property should have.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   An entity query for search tasks.
   */
  protected function getTasksQuery(array $conditions = []) {
    $query = $this->getTaskStorage()->getQuery()->accessCheck(FALSE);
    foreach ($conditions as $property => $values) {
      if ($values === NULL) {
        $query->notExists($property);
      }
      else {
        $query->condition($property, $values, is_array($values) ? 'IN' : '=');
      }
    }
    $query->sort('id');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getTasksCount(array $conditions = []) {
    return $this->getTasksQuery($conditions)
      ->count()
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function addTask($type, ?ServerInterface $server = NULL, ?IndexInterface $index = NULL, $data = NULL) {
    $server_id = $server?->id();
    $index_id = $index?->id();
    if (isset($data)) {
      if ($data instanceof EntityInterface) {
        $data = [
          '#entity_type' => $data->getEntityTypeId(),
          '#values' => $data->toArray(),
        ];
      }
      $data = serialize($data);
    }

    $result = $this->getTasksQuery([
      'type' => $type,
      'server_id' => $server_id,
      'index_id' => $index_id,
      'data' => $data,
    ])->execute();
    if ($result) {
      return $this->getTaskStorage()->load(reset($result));
    }

    $task = $this->getTaskStorage()->create([
      'type' => $type,
      'server_id' => $server_id,
      'index_id' => $index_id,
      'data' => $data,
    ]);
    $task->save();
    return $task;
  }

  /**
   * {@inheritdoc}
   */
  public function loadTasks(array $conditions = []) {
    $task_ids = $this->getTasksQuery($conditions)->execute();
    if ($task_ids) {
      return $this->getTaskStorage()->loadMultiple($task_ids);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTask($task_id) {
    $this->getTaskStorage()->load($task_id)?->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTasks(array $conditions = []) {
    $storage = $this->getTaskStorage();
    while (TRUE) {
      $task_ids = $this->getTasksQuery($conditions)
        ->range(0, 100)
        ->execute();
      if (!$task_ids) {
        break;
      }
      $tasks = $storage->loadMultiple($task_ids);
      $storage->delete($tasks);
      if (count($task_ids) < 100) {
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeSpecificTask(TaskInterface $task) {
    // Do not attempt to execute a task if one is currently being executed.
    if (static::$hasActiveTask) {
      return;
    }

    $event = new TaskEvent($task);
    static::$hasActiveTask = TRUE;
    $this->eventDispatcher->dispatch($event, 'search_api.task.' . $task->getType());
    static::$hasActiveTask = FALSE;
    if (!$event->isPropagationStopped()) {
      $id = $task->id();
      $type = $task->getType();
      throw new SearchApiException("Could not execute task #$id of type '$type'. Type seems to be unknown.");
    }
    if ($exception = $event->getException()) {
      throw $exception;
    }
    $task->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function executeSingleTask(array $conditions = []) {
    $task_id = $this->getTasksQuery($conditions)->range(0, 1)->execute();
    if ($task_id) {
      $task_id = reset($task_id);
      /** @var \Drupal\search_api\Task\TaskInterface $task */
      $task = $this->getTaskStorage()->load($task_id);
      $this->executeSpecificTask($task);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAllTasks(array $conditions = [], $limit = NULL) {
    // Just ignore all tasks while a task is currently being executed.
    if (static::$hasActiveTask) {
      return TRUE;
    }

    // We have to use this roundabout way because tasks, during their execution,
    // might create additional tasks. (For example, see
    // \Drupal\search_api\Task\IndexTaskManager::trackItems().)
    $executed = 0;
    while (TRUE) {
      $query = $this->getTasksQuery($conditions);
      if (isset($limit)) {
        $query->range(0, $limit - $executed);
      }
      $task_ids = $query->execute();

      if (!$task_ids) {
        break;
      }

      // We can't use multi-load here as a task might delete other tasks, so we
      // have to make sure each tasks still exists right before it is executed.
      foreach ($task_ids as $task_id) {
        /** @var \Drupal\search_api\Task\TaskInterface $task */
        $task = $this->getTaskStorage()->load($task_id);
        if ($task) {
          $this->executeSpecificTask($task);
        }
        else {
          --$executed;
        }
      }

      $executed += count($task_ids);
      if (isset($limit) && $executed >= $limit) {
        break;
      }
    }

    return !$this->getTasksCount($conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function setTasksBatch(array $conditions = []) {
    // We don't want to set a batch during an installation or update hook.
    if (defined('MAINTENANCE_MODE')
        && in_array(MAINTENANCE_MODE, ['install', 'update'])) {
      return;
    }

    $task_ids = $this->getTasksQuery($conditions)->range(0, 100)->execute();

    if (!$task_ids) {
      return;
    }

    $batch_definition = [
      'operations' => [
        [[$this, 'processBatch'], [$task_ids, $conditions]],
      ],
      'finished' => [$this, 'finishBatch'],
    ];

    // If called inside of Drush, we want to start the batch immediately.
    // However, we first need to determine whether there already is one running,
    // since we don't want to start a second one – our new batch will
    // automatically be appended to the currently running batch operation.
    $batch = batch_get();
    $run_drush_batch = function_exists('drush_backend_batch_process')
      && empty($batch['running']);

    // Schedule the batch.
    batch_set($batch_definition);

    // Now run the Drush batch, if applicable.
    if ($run_drush_batch) {
      $result = drush_backend_batch_process();
      // Drush performs batch processing in a separate PHP request. When the
      // last batch is processed the batch list is cleared, but this only takes
      // effect in the other request. Take the same action here to ensure that
      // we are not requeuing stale batches when there are multiple tasks being
      // handled in a single request.
      // (Drush 9.6 changed the structure of $result, so check for both variants
      // as long as we support earlier Drush versions, too.)
      if (!empty($result['context']['drush_batch_process_finished'])
          || !empty($result['drush_batch_process_finished'])) {
        $batch = &batch_get();
        $batch = NULL;
        unset($batch);
      }
    }
  }

  /**
   * Processes a single pending task as part of a batch operation.
   *
   * @param int[] $task_ids
   *   An array of task IDs to execute. Might not contain all task IDs.
   * @param array $conditions
   *   An array of conditions defining the tasks to be executed. Should be used
   *   to retrieve more task IDs if necessary.
   * @param array|\ArrayAccess $context
   *   The context of the current batch, as defined in the @link batch Batch
   *   operations @endlink documentation.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if any error occurred while processing the task.
   */
  public function processBatch(array $task_ids, array $conditions, &$context) {
    // Initialize context information.
    if (!isset($context['sandbox']['task_ids'])) {
      $context['sandbox']['task_ids'] = $task_ids;
    }
    if (!isset($context['results']['total'])) {
      $context['results']['total'] = $this->getTasksCount($conditions);
    }

    $task_id = array_shift($context['sandbox']['task_ids']);
    /** @var \Drupal\search_api\Task\TaskInterface $task */
    $task = $this->getTaskStorage()->load($task_id);

    if ($task) {
      $this->executeSpecificTask($task);
    }

    if (!$context['sandbox']['task_ids']) {
      $context['sandbox']['task_ids'] = $this->getTasksQuery($conditions)
        ->range(0, 100)
        ->execute();
      if (!$context['sandbox']['task_ids']) {
        $context['finished'] = 1;
        return;
      }
    }

    $pending = $this->getTasksCount($conditions);
    // Guard against a total count of 0, which sometimes happens.
    $context['results']['total'] = max($context['results']['total'], $pending);
    if ($context['results']['total'] > 0) {
      $context['finished'] = 1 - $pending / $context['results']['total'];
    }
    else {
      $context['finished'] = 1;
    }
    $executed = $context['results']['total'] - $pending;
    if ($executed > 0) {
      $context['message'] = $this->formatPlural(
        $executed,
        'Successfully executed @count pending task.',
        'Successfully executed @count pending tasks.'
      );
    }
  }

  /**
   * Finishes an "execute tasks" batch.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   */
  public function finishBatch($success, array $results) {
    // Check if the batch job was successful.
    if ($success) {
      $message = $this->formatPlural(
        $results['total'],
        'Successfully executed @count pending task.',
        'Successfully executed @count pending tasks.'
      );
      $this->messenger->addStatus($message);
    }
    else {
      // Notify the user about the batch job failure.
      $this->messenger->addError($this->t('An error occurred while trying to execute tasks. Check the logs for details.'));
    }
  }

}
