<?php

namespace Drupal\Tests\search_api\Kernel\System;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Task\TaskInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests whether the Search API task system works correctly.
 *
 * @see \Drupal\search_api_test_tasks\TestTaskWorker
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class TaskTest extends KernelTestBase {

  /**
   * The test server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'search_api',
    'search_api_test',
    'search_api_test_tasks',
  ];

  /**
   * The task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\TaskManagerInterface
   */
  protected $taskManager;

  /**
   * The test task worker service.
   *
   * @var \Drupal\search_api_test_tasks\TestTaskWorker
   */
  protected $taskWorker;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');

    $this->taskManager = $this->container->get('search_api.task_manager');
    $this->taskWorker = $this->container->get('search_api_test_tasks.test_task_worker');

    // Create a test server.
    $this->server = Server::create([
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
    ]);
    $this->server->save();

    // Create a test index.
    $this->index = Index::create([
      'name' => 'Test index',
      'id' => 'test_index',
      'status' => 0,
      'datasource_settings' => [
        'entity:user' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
    ]);
    $this->index->save();
  }

  /**
   * Tests successful task execution.
   */
  public function testTaskSuccess() {
    $task = $this->addTask('success');
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->taskManager->executeSingleTask();
    $this->assertEquals(0, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests failed task execution.
   */
  public function testTaskFail() {
    $task = $this->addTask('fail', $this->server);
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks([
        'server_id' => $this->server->id(),
      ]);
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests ignored task execution.
   */
  public function testTaskIgnored() {
    $task = $this->addTask('ignore', NULL, $this->index, 'foobar');
    $type = $task->getType();
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks([
        'type' => [$type, 'unknown'],
        'index_id' => $this->index->id(),
      ]);
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $id = $task->id();
      $this->assertEquals("Could not execute task #$id of type '$type'. Type seems to be unknown.", $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals($task->toArray(), $this->taskWorker->getEventLog()[0]);
  }

  /**
   * Tests unknown task execution.
   */
  public function testTaskUnknown() {
    $task = $this->addTask('unknown');
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $id = $task->id();
      $type = $task->getType();
      $this->assertEquals("Could not execute task #$id of type '$type'. Type seems to be unknown.", $e->getMessage());
    }
    $this->assertEquals(1, $this->taskManager->getTasksCount());
    $this->assertEquals([], $this->taskWorker->getEventLog());
  }

  /**
   * Tests that duplicate tasks won't be created.
   */
  public function testTaskDuplicates() {
    $data = ['foo' => 'bar', 1];
    $task1 = $this->addTask('success');
    $task2 = $this->addTask('success', duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', data: $data);
    $task2 = $this->addTask('success', data: $data, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', $this->server);
    $task2 = $this->addTask('success', $this->server, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', $this->server, data: $data);
    $task2 = $this->addTask('success', $this->server, data: $data, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', index: $this->index);
    $task2 = $this->addTask('success', index: $this->index, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', index: $this->index, data: $data);
    $task2 = $this->addTask('success', index: $this->index, data: $data, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', $this->server, $this->index);
    $task2 = $this->addTask('success', $this->server, $this->index, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $task1 = $this->addTask('success', $this->server, $this->index, $data);
    $task2 = $this->addTask('success', $this->server, $this->index, $data, TRUE);
    $this->assertEquals($task1->id(), $task2->id());
    $data[] = 2;
    $task1 = $this->addTask('success', data: $data);
    $task2 = $this->addTask('success', data: $data, duplicate: TRUE);
    $this->assertEquals($task1->id(), $task2->id());
  }

  /**
   * Tests that multiple pending tasks are treated correctly.
   */
  public function testMultipleTasks() {
    // Add some tasks to the system. We use explicit indexes since we want to
    // verify that the tasks are executed in a different order than the one they
    // were added, if appropriate $conditions parameters are given.
    $tasks = [];
    $tasks[0] = $this->addTask('success', $this->server, $this->index, ['foo' => 1, 'bar']);
    $tasks[6] = $this->addTask('fail');
    $tasks[1] = $this->addTask('success', $this->server, data: TRUE);
    $tasks[4] = $this->addTask('success', data: 1);
    $tasks[2] = $this->addTask('fail', $this->server, $this->index);
    $tasks[5] = $this->addTask('success');
    $tasks[3] = $this->addTask('success', index: $this->index);

    $num = count($tasks);
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask([
      'server_id' => $this->server->id(),
    ]);
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    try {
      $this->taskManager->executeAllTasks([
        'server_id' => $this->server->id(),
      ]);
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $tasks[2]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeSingleTask([
      'index_id' => $this->index->id(),
    ]);
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeAllTasks([
      'type' => ['search_api_test_tasks.success', 'foobar'],
    ]);
    $this->assertEquals($num -= 2, $this->taskManager->getTasksCount());

    // Need to include some data so the new task won't count as a duplicate.
    $tasks[7] = $this->addTask('success', data: 1);
    $tasks[8] = $this->addTask('success', data: 2);
    $tasks[9] = $this->addTask('fail', data: 3);
    $tasks[10] = $this->addTask('success', data: 4);
    $num += 4;

    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num, $this->taskManager->getTasksCount());

    $tasks[6]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    try {
      $this->taskManager->executeAllTasks();
      $this->fail('Exception expected');
    }
    catch (SearchApiException $e) {
      $this->assertEquals('fail', $e->getMessage());
    }
    $this->assertEquals($num -= 2, $this->taskManager->getTasksCount());

    $tasks[9]->delete();
    $this->assertEquals(--$num, $this->taskManager->getTasksCount());

    $this->taskManager->executeAllTasks();
    $this->assertEquals(0, $this->taskManager->getTasksCount());

    $to_array = function (TaskInterface $task) {
      return $task->toArray();
    };
    $tasks = array_map($to_array, $tasks);
    $this->assertEquals($tasks, $this->taskWorker->getEventLog());
  }

  /**
   * Adds a new pending task.
   *
   * @param string $type
   *   The type of task, without "search_api_test_tasks." prefix.
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The search server associated with the task, if any.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (optional) The search index associated with the task, if any.
   * @param mixed|null $data
   *   (optional) Additional, type-specific data to save with the task.
   * @param bool $duplicate
   *   (optional) TRUE if the task is expected to be a duplicate and not
   *   created.
   *
   * @return \Drupal\search_api\Task\TaskInterface
   *   The task returned by the task manager.
   */
  protected function addTask($type, ?ServerInterface $server = NULL, ?IndexInterface $index = NULL, $data = NULL, bool $duplicate = FALSE) {
    $type = "search_api_test_tasks.$type";
    $count_before = $this->taskManager->getTasksCount();
    $conditions = [
      'type' => $type,
      'server_id' => $server?->id(),
      'index_id' => $index?->id(),
    ];
    $conditions = array_filter($conditions);
    $count_before_conditions = $this->taskManager->getTasksCount($conditions);

    $task = $this->taskManager->addTask($type, $server, $index, $data);

    $delta = $duplicate ? 0 : 1;
    $count_after = $this->taskManager->getTasksCount();
    $this->assertEquals($count_before + $delta, $count_after);
    $count_after_conditions = $this->taskManager->getTasksCount($conditions);
    $this->assertEquals($count_before_conditions + $delta, $count_after_conditions);

    return $task;
  }

}
