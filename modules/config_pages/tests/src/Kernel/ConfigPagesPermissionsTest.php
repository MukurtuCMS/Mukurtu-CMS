<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\ConfigPagesPermissions;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesPermissions.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\ConfigPagesPermissions
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesPermissionsTest extends KernelTestBase {

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
   * The permissions instance.
   *
   * @var \Drupal\config_pages\ConfigPagesPermissions
   */
  protected ConfigPagesPermissions $permissionsHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    $this->permissionsHandler = ConfigPagesPermissions::create($this->container);
  }

  /**
   * Tests that permissions handler can be created.
   */
  public function testPermissionsHandlerCanBeCreated(): void {
    $this->assertInstanceOf(ConfigPagesPermissions::class, $this->permissionsHandler);
  }

  /**
   * Tests permissions returns empty array when no types exist.
   *
   * @covers ::permissions
   */
  public function testPermissionsReturnsEmptyWhenNoTypes(): void {
    $permissions = $this->permissionsHandler->permissions();

    $this->assertIsArray($permissions);
    $this->assertEmpty($permissions);
  }

  /**
   * Tests permissions are generated for config page type.
   *
   * @covers ::permissions
   */
  public function testPermissionsGeneratedForType(): void {
    // Create a config page type.
    $type = ConfigPagesType::create([
      'id' => 'test_perm_type',
      'label' => 'Test Permissions Type',
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
    ]);
    $type->save();

    $permissions = $this->permissionsHandler->permissions();

    $this->assertArrayHasKey('view test_perm_type config page entity', $permissions);
    $this->assertArrayHasKey('edit test_perm_type config page entity', $permissions);
  }

  /**
   * Tests view permission structure.
   *
   * @covers ::permissions
   */
  public function testViewPermissionStructure(): void {
    $type = ConfigPagesType::create([
      'id' => 'test_view_perm',
      'label' => 'Test View Permissions',
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
    ]);
    $type->save();

    $permissions = $this->permissionsHandler->permissions();
    $viewPerm = $permissions['view test_view_perm config page entity'];

    $this->assertArrayHasKey('title', $viewPerm);
    $this->assertStringContainsString('View', (string) $viewPerm['title']);
    $this->assertStringContainsString('Test View Permissions', (string) $viewPerm['title']);
  }

  /**
   * Tests edit permission structure.
   *
   * @covers ::permissions
   */
  public function testEditPermissionStructure(): void {
    $type = ConfigPagesType::create([
      'id' => 'test_edit_perm',
      'label' => 'Test Edit Permissions',
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
    ]);
    $type->save();

    $permissions = $this->permissionsHandler->permissions();
    $editPerm = $permissions['edit test_edit_perm config page entity'];

    $this->assertArrayHasKey('title', $editPerm);
    $this->assertStringContainsString('Edit', (string) $editPerm['title']);
    $this->assertStringContainsString('Test Edit Permissions', (string) $editPerm['title']);
  }

  /**
   * Tests permissions for multiple types.
   *
   * @covers ::permissions
   */
  public function testPermissionsForMultipleTypes(): void {
    // Create first type.
    $type1 = ConfigPagesType::create([
      'id' => 'multi_type_one',
      'label' => 'Multi Type One',
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
    ]);
    $type1->save();

    // Create second type.
    $type2 = ConfigPagesType::create([
      'id' => 'multi_type_two',
      'label' => 'Multi Type Two',
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
    ]);
    $type2->save();

    $permissions = $this->permissionsHandler->permissions();

    // Should have 4 permissions (2 per type).
    $this->assertCount(4, $permissions);

    $this->assertArrayHasKey('view multi_type_one config page entity', $permissions);
    $this->assertArrayHasKey('edit multi_type_one config page entity', $permissions);
    $this->assertArrayHasKey('view multi_type_two config page entity', $permissions);
    $this->assertArrayHasKey('edit multi_type_two config page entity', $permissions);
  }

  /**
   * Tests that static permissions are defined in yml file.
   */
  public function testStaticPermissionsExist(): void {
    $permissionHandler = $this->container->get('user.permissions');
    $permissions = $permissionHandler->getPermissions();

    $this->assertArrayHasKey('view config_pages entity', $permissions);
    $this->assertArrayHasKey('edit config_pages entity', $permissions);
    $this->assertArrayHasKey('delete config_pages entity', $permissions);
    $this->assertArrayHasKey('administer config_pages types', $permissions);
    $this->assertArrayHasKey('access config_pages overview', $permissions);
  }

  /**
   * Tests dynamic permissions are registered with permission handler.
   */
  public function testDynamicPermissionsRegistered(): void {
    // Create a type.
    $type = ConfigPagesType::create([
      'id' => 'dynamic_perm_test',
      'label' => 'Dynamic Perm Test',
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
    ]);
    $type->save();

    // Rebuild container to pick up new permissions.
    $this->container->get('kernel')->rebuildContainer();

    $permissionHandler = \Drupal::service('user.permissions');
    $permissions = $permissionHandler->getPermissions();

    $this->assertArrayHasKey('view dynamic_perm_test config page entity', $permissions);
    $this->assertArrayHasKey('edit dynamic_perm_test config page entity', $permissions);
  }

}
