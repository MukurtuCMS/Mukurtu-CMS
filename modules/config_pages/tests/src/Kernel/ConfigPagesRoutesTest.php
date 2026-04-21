<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Routing\ConfigPagesRoutes;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Kernel tests for ConfigPagesRoutes.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Routing\ConfigPagesRoutes
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesRoutesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'config_pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);
  }

  /**
   * Tests routes returns empty array when no types exist.
   *
   * @covers ::routes
   */
  public function testRoutesEmptyWhenNoTypes(): void {
    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();

    $this->assertIsArray($routes);
    $this->assertEmpty($routes);
  }

  /**
   * Tests route generation with custom menu path.
   *
   * @covers ::routes
   */
  public function testRouteWithCustomMenuPath(): void {
    ConfigPagesType::create([
      'id' => 'test_custom_path',
      'label' => 'Test Custom Path',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/custom-settings',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();

    $this->assertArrayHasKey('config_pages.test_custom_path', $routes);

    $route = $routes['config_pages.test_custom_path'];
    $this->assertInstanceOf(Route::class, $route);
    $this->assertEquals('/admin/config/custom-settings', $route->getPath());
  }

  /**
   * Tests route fallback to default path when menu path is empty.
   *
   * @covers ::routes
   */
  public function testRouteFallbackWhenMenuPathEmpty(): void {
    ConfigPagesType::create([
      'id' => 'test_no_path',
      'label' => 'Test No Path',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();

    $route = $routes['config_pages.test_no_path'];
    $this->assertEquals('/admin/structure/config_pages/test_no_path', $route->getPath());
  }

  /**
   * Tests route has correct controller default.
   *
   * @covers ::routes
   */
  public function testRouteControllerDefault(): void {
    ConfigPagesType::create([
      'id' => 'test_defaults',
      'label' => 'Test Defaults',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();
    $route = $routes['config_pages.test_defaults'];

    $defaults = $route->getDefaults();
    $this->assertEquals(
      '\Drupal\config_pages\Controller\ConfigPagesController::classInit',
      $defaults['_controller']
    );
    $this->assertEquals('test_defaults', $defaults['config_pages_type']);
    $this->assertEquals(
      '\Drupal\config_pages\Controller\ConfigPagesController::getPageTitle',
      $defaults['_title_callback']
    );
    $this->assertEquals('Test Defaults', $defaults['label']);
  }

  /**
   * Tests route has correct access requirement.
   *
   * @covers ::routes
   */
  public function testRouteAccessRequirement(): void {
    ConfigPagesType::create([
      'id' => 'test_access',
      'label' => 'Test Access',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();
    $route = $routes['config_pages.test_access'];

    $requirements = $route->getRequirements();
    $this->assertEquals(
      '\Drupal\config_pages\Controller\ConfigPagesController::access',
      $requirements['_custom_access']
    );
  }

  /**
   * Tests route is marked as admin route.
   *
   * @covers ::routes
   */
  public function testRouteIsAdminRoute(): void {
    ConfigPagesType::create([
      'id' => 'test_admin',
      'label' => 'Test Admin',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();
    $route = $routes['config_pages.test_admin'];

    $this->assertTrue($route->getOption('_admin_route'));
  }

  /**
   * Tests multiple types generate multiple routes.
   *
   * @covers ::routes
   */
  public function testMultipleTypesGenerateMultipleRoutes(): void {
    ConfigPagesType::create([
      'id' => 'type_one',
      'label' => 'Type One',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/type-one',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    ConfigPagesType::create([
      'id' => 'type_two',
      'label' => 'Type Two',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ])->save();

    $routeProvider = new ConfigPagesRoutes();
    $routes = $routeProvider->routes();

    $this->assertCount(2, $routes);
    $this->assertArrayHasKey('config_pages.type_one', $routes);
    $this->assertArrayHasKey('config_pages.type_two', $routes);

    // First uses custom path, second uses fallback.
    $this->assertEquals('/admin/config/type-one', $routes['config_pages.type_one']->getPath());
    $this->assertEquals('/admin/structure/config_pages/type_two', $routes['config_pages.type_two']->getPath());
  }

}
