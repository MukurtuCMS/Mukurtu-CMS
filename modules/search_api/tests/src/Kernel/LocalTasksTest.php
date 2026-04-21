<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests whether Search API's local tasks work correctly.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class LocalTasksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['search_api'];

  /**
   * Tests whether the server's local tasks are present at the given route.
   *
   * @param string $route
   *   The route to test.
   *
   * @dataProvider getPageRoutesServer
   */
  public function testLocalTasksServer(string $route): void {
    $tasks = [
      0 => [
        'entity.search_api_server.canonical',
        'entity.search_api_server.edit_form',
      ],
    ];
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provides a list of routes to test.
   *
   * @return array[]
   *   An array containing arrays with the arguments for a
   *   testLocalTasksServer() call.
   */
  public static function getPageRoutesServer(): array {
    return [
      ['entity.search_api_server.canonical'],
      ['entity.search_api_server.edit_form'],
    ];
  }

  /**
   * Tests whether the index's local tasks are present at the given route.
   *
   * @param string $route
   *   The route to test.
   *
   * @dataProvider getPageRoutesIndex
   */
  public function testLocalTasksIndex(string $route): void {
    $tasks = [
      0 => [
        'entity.search_api_index.canonical',
        'entity.search_api_index.edit_form',
        'entity.search_api_index.fields',
        'entity.search_api_index.processors',
      ],
    ];
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provides a list of routes to test.
   *
   * @return array[]
   *   An array containing arrays with the arguments for a
   *   testLocalTasksIndex() call.
   */
  public static function getPageRoutesIndex(): array {
    return [
      ['entity.search_api_index.canonical'],
      ['entity.search_api_index.edit_form'],
      ['entity.search_api_index.fields'],
      ['entity.search_api_index.processors'],
    ];
  }

  /**
   * Asserts integration for local tasks.
   *
   * @param $route_name
   *   Route name to base task building on.
   * @param $expected_tasks
   *   A list of tasks groups by level expected at the given route.
   */
  protected function assertLocalTasks(string $route_name, array $expected_tasks): void {
    $manager = $this->container->get('plugin.manager.menu.local_task');
    $route_tasks = array_map(function (array $tasks): array {
      return array_keys($tasks);
    }, $manager->getLocalTasksForRoute($route_name));
    $this->assertSame($expected_tasks, $route_tasks);
  }

}
