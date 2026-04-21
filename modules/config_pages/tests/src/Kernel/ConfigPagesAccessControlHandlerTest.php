<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Kernel tests for ConfigPagesAccessControlHandler.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\ConfigPagesAccessControlHandler
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesAccessControlHandlerTest extends KernelTestBase {

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
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system', 'user']);
    $this->installSchema('user', ['users_data']);

    // Create user 1 (superuser) first to avoid test users getting UID 1.
    $superuser = User::create([
      'uid' => 1,
      'name' => 'superuser',
      'mail' => 'superuser@example.com',
      'status' => 1,
    ]);
    $superuser->save();

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_access_type',
      'label' => 'Test Access Type',
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
    $this->configPageType->save();

    // Create a config page entity.
    $this->configPage = ConfigPages::create([
      'type' => 'test_access_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $this->configPage->save();
  }

  /**
   * Creates a user with specific permissions.
   *
   * @param array $permissions
   *   Permissions to grant.
   *
   * @return \Drupal\user\Entity\User
   *   The created user.
   */
  protected function createUserWithPermissions(array $permissions): User {
    // Create a role with the permissions.
    $role = Role::create([
      'id' => 'test_role_' . md5(implode(',', $permissions)),
      'label' => 'Test Role',
    ]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    // Create a user with the role.
    $user = User::create([
      'name' => 'test_user_' . $role->id(),
      'mail' => 'test_' . $role->id() . '@example.com',
      'status' => 1,
    ]);
    $user->addRole($role->id());
    $user->save();

    return $user;
  }

  /**
   * Tests view access with global view permission.
   *
   * @covers ::checkAccess
   */
  public function testViewAccessWithGlobalPermission(): void {
    $user = $this->createUserWithPermissions(['view config_pages entity']);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests view access with type-specific permission.
   *
   * @covers ::checkAccess
   */
  public function testViewAccessWithTypeSpecificPermission(): void {
    $user = $this->createUserWithPermissions(['view test_access_type config page entity']);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests view access denied without permission.
   *
   * @covers ::checkAccess
   */
  public function testViewAccessDeniedWithoutPermission(): void {
    $user = $this->createUserWithPermissions([]);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests view access denied with wrong type-specific permission.
   *
   * @covers ::checkAccess
   */
  public function testViewAccessDeniedWithWrongTypePermission(): void {
    $user = $this->createUserWithPermissions(['view other_type config page entity']);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests update access with global edit permission.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessWithGlobalPermission(): void {
    $user = $this->createUserWithPermissions(['edit config_pages entity']);

    $access = $this->configPage->access('update', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests update access with type-specific permission.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessWithTypeSpecificPermission(): void {
    $user = $this->createUserWithPermissions(['edit test_access_type config page entity']);

    $access = $this->configPage->access('update', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests update access denied without permission.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessDeniedWithoutPermission(): void {
    $user = $this->createUserWithPermissions([]);

    $access = $this->configPage->access('update', $user, TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests update access denied with wrong type-specific permission.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessDeniedWithWrongTypePermission(): void {
    $user = $this->createUserWithPermissions(['edit other_type config page entity']);

    $access = $this->configPage->access('update', $user, TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests create access with global edit permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAccessWithGlobalPermission(): void {
    $user = $this->createUserWithPermissions(['edit config_pages entity']);

    $accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('config_pages');
    $access = $accessHandler->createAccess('test_access_type', $user, [], TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests create access with type-specific permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAccessWithTypeSpecificPermission(): void {
    $user = $this->createUserWithPermissions(['edit test_access_type config page entity']);

    $accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('config_pages');
    $access = $accessHandler->createAccess('test_access_type', $user, [], TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests create access denied without permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAccessDeniedWithoutPermission(): void {
    $user = $this->createUserWithPermissions([]);

    $accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('config_pages');
    $access = $accessHandler->createAccess('test_access_type', $user, [], TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests create access denied with wrong type-specific permission.
   *
   * @covers ::checkCreateAccess
   */
  public function testCreateAccessDeniedWithWrongTypePermission(): void {
    $user = $this->createUserWithPermissions(['edit other_type config page entity']);

    $accessHandler = $this->container->get('entity_type.manager')
      ->getAccessControlHandler('config_pages');
    $access = $accessHandler->createAccess('test_access_type', $user, [], TRUE);

    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests that access result is cacheable per permissions.
   *
   * @covers ::checkAccess
   */
  public function testAccessCacheability(): void {
    $user = $this->createUserWithPermissions(['view config_pages entity']);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertTrue($access->isAllowed());
    $this->assertContains('user.permissions', $access->getCacheContexts());
  }

  /**
   * Tests view access preference: global over type-specific.
   *
   * @covers ::checkAccess
   */
  public function testViewAccessPriority(): void {
    // User has both permissions, should still work.
    $user = $this->createUserWithPermissions([
      'view config_pages entity',
      'view test_access_type config page entity',
    ]);

    $access = $this->configPage->access('view', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests update access for config_pages_type entity.
   *
   * @covers ::checkAccess
   */
  public function testUpdateAccessForConfigPagesType(): void {
    $user = $this->createUserWithPermissions(['edit test_access_type config page entity']);

    $access = $this->configPageType->access('update', $user, TRUE);

    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that anonymous user has no access without permissions.
   *
   * @covers ::checkAccess
   */
  public function testAnonymousUserNoAccess(): void {
    $anonymousUser = User::getAnonymousUser();

    $viewAccess = $this->configPage->access('view', $anonymousUser, TRUE);
    $updateAccess = $this->configPage->access('update', $anonymousUser, TRUE);

    $this->assertFalse($viewAccess->isAllowed());
    $this->assertFalse($updateAccess->isAllowed());
  }

  /**
   * Tests delete operation falls through to parent handler.
   *
   * @covers ::checkAccess
   */
  public function testDeleteAccessFallsToParent(): void {
    // Delete is not explicitly handled, so it should fall through to parent.
    $user = $this->createUserWithPermissions([]);

    $access = $this->configPage->access('delete', $user, TRUE);

    // Without administer permission, delete should be denied.
    $this->assertFalse($access->isAllowed());
  }

  /**
   * Tests access with multiple config page types.
   *
   * @covers ::checkAccess
   */
  public function testAccessWithMultipleTypes(): void {
    // Create another type.
    $anotherType = ConfigPagesType::create([
      'id' => 'another_type',
      'label' => 'Another Type',
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
    $anotherType->save();

    $anotherPage = ConfigPages::create([
      'type' => 'another_type',
      'label' => 'Another Config Page',
      'context' => serialize([]),
    ]);
    $anotherPage->save();

    // User can only view the first type.
    $user = $this->createUserWithPermissions(['view test_access_type config page entity']);

    $accessOriginal = $this->configPage->access('view', $user, TRUE);
    $accessAnother = $anotherPage->access('view', $user, TRUE);

    $this->assertTrue($accessOriginal->isAllowed());
    $this->assertFalse($accessAnother->isAllowed());
  }

}
