<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesListBuilder and ConfigPagesTypeListBuilder.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesListBuilderTest extends KernelTestBase {

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
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_list',
      'label' => 'Test List Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config/test-list',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();
  }

  /**
   * Tests ConfigPagesListBuilder buildHeader.
   */
  public function testConfigPagesListBuilderHeader(): void {
    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $header = $listBuilder->buildHeader();

    $this->assertArrayHasKey('label', $header);
    $this->assertArrayHasKey('context', $header);
    $this->assertArrayHasKey('token', $header);
    $this->assertArrayHasKey('operations', $header);
  }

  /**
   * Tests ConfigPagesListBuilder load returns types, not entities.
   */
  public function testConfigPagesListBuilderLoad(): void {
    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $entities = $listBuilder->load();

    // Load should return config page types for listing.
    $this->assertNotEmpty($entities);
    $this->assertArrayHasKey('test_list', $entities);
    $this->assertInstanceOf(ConfigPagesType::class, $entities['test_list']);
  }

  /**
   * Tests ConfigPagesListBuilder buildRow with token hidden.
   */
  public function testConfigPagesListBuilderRowTokenHidden(): void {
    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $row = $listBuilder->buildRow($this->configPageType);

    $this->assertEquals('Test List Type', $row['label']);
    $this->assertEquals('', $row['context']);
    $this->assertEquals('Hidden', $row['token']);
  }

  /**
   * Tests ConfigPagesListBuilder buildRow with token exposed.
   */
  public function testConfigPagesListBuilderRowTokenExposed(): void {
    $exposedType = ConfigPagesType::create([
      'id' => 'test_exposed',
      'label' => 'Test Exposed Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => TRUE,
    ]);
    $exposedType->save();

    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $row = $listBuilder->buildRow($exposedType);

    $this->assertEquals('Exposed', $row['token']);
  }

  /**
   * Tests ConfigPagesListBuilder getOperations with custom menu path.
   */
  public function testConfigPagesListBuilderOperationsWithMenuPath(): void {
    // Create a user with edit permission.
    $account = $this->createUser(['edit config_pages entity']);
    $this->container->get('current_user')->setAccount($account);

    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $operations = $listBuilder->getOperations($this->configPageType);

    $this->assertArrayHasKey('edit', $operations);
    $this->assertEquals('/admin/config/test-list', $operations['edit']['url']->toString());
  }

  /**
   * Tests ConfigPagesListBuilder getOperations without menu path.
   */
  public function testConfigPagesListBuilderOperationsWithoutMenuPath(): void {
    $noPathType = ConfigPagesType::create([
      'id' => 'test_no_path',
      'label' => 'No Path Type',
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
    $noPathType->save();

    $account = $this->createUser(['edit config_pages entity']);
    $this->container->get('current_user')->setAccount($account);

    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $operations = $listBuilder->getOperations($noPathType);

    $this->assertArrayHasKey('edit', $operations);
    $this->assertEquals('config_pages.add_form', $operations['edit']['url']->getRouteName());
  }

  /**
   * Tests ConfigPagesListBuilder getOperations without permission.
   */
  public function testConfigPagesListBuilderOperationsNoPermission(): void {
    $account = $this->createUser([]);
    $this->container->get('current_user')->setAccount($account);

    /** @var \Drupal\config_pages\ConfigPagesListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages');

    $operations = $listBuilder->getOperations($this->configPageType);

    $this->assertEmpty($operations);
  }

  /**
   * Tests ConfigPagesTypeListBuilder buildHeader.
   */
  public function testTypeListBuilderHeader(): void {
    /** @var \Drupal\config_pages\ConfigPagesTypeListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages_type');

    $header = $listBuilder->buildHeader();

    $this->assertArrayHasKey('type', $header);
    $this->assertArrayHasKey('context', $header);
    $this->assertArrayHasKey('token', $header);
    $this->assertArrayHasKey('operations', $header);
  }

  /**
   * Tests ConfigPagesTypeListBuilder buildRow.
   */
  public function testTypeListBuilderRow(): void {
    /** @var \Drupal\config_pages\ConfigPagesTypeListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages_type');

    $row = $listBuilder->buildRow($this->configPageType);

    $this->assertArrayHasKey('type', $row);
    $this->assertEquals('', $row['context']);
    $this->assertEquals('Hidden', $row['token']);
  }

  /**
   * Tests ConfigPagesTypeListBuilder getDefaultOperations edit weight.
   */
  public function testTypeListBuilderEditWeight(): void {
    /** @var \Drupal\config_pages\ConfigPagesTypeListBuilder $listBuilder */
    $listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('config_pages_type');

    $operations = $listBuilder->getDefaultOperations($this->configPageType);

    if (isset($operations['edit'])) {
      $this->assertEquals(30, $operations['edit']['weight']);
    }
  }

  /**
   * Creates a user entity for testing.
   *
   * @param array $permissions
   *   Permissions to grant.
   *
   * @return \Drupal\user\Entity\User
   *   The created user entity.
   */
  protected function createUser(array $permissions = []) {
    $this->installSchema('user', ['users_data']);

    // Ensure uid 1 (super admin) is taken by a placeholder user.
    if (!User::load(1)) {
      User::create([
        'uid' => 1,
        'name' => 'admin',
        'status' => 1,
      ])->save();
    }

    /** @var \Drupal\user\Entity\User $user */
    $user = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);

    // Grant permissions via a role.
    if (!empty($permissions)) {
      $role = Role::create([
        'id' => 'test_role',
        'label' => 'Test Role',
      ]);
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
      $user->addRole('test_role');
    }

    $user->save();
    return $user;
  }

}
