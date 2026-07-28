<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests mukurtu_update_40002(), which revokes article content permissions.
 *
 * @see mukurtu_update_40002()
 * @group mukurtu_core
 */
class ArticlePermissionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The article content permissions the update hook should revoke.
   */
  protected const ARTICLE_PERMISSIONS = [
    'create article content',
    'delete article revisions',
    'delete own article content',
    'edit own article content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    require_once $profile_path . '/mukurtu.install';
  }

  /**
   * The update hook revokes all four article permissions.
   */
  public function testUpdateRevokesArticlePermissions(): void {
    $role = Role::create(['id' => 'content_editor', 'label' => 'Content Editor']);
    foreach (self::ARTICLE_PERMISSIONS as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    mukurtu_update_40002();

    $role = Role::load('content_editor');
    foreach (self::ARTICLE_PERMISSIONS as $permission) {
      $this->assertFalse($role->hasPermission($permission), "Expected '$permission' to be revoked.");
    }
  }

  /**
   * The update hook is a no-op when the content_editor role doesn't exist.
   */
  public function testUpdateIsNoOpWithoutRole(): void {
    $this->assertNull(Role::load('content_editor'));
    mukurtu_update_40002();
    $this->assertNull(Role::load('content_editor'));
  }

  /**
   * The update hook leaves unrelated permissions on the role untouched.
   */
  public function testUpdatePreservesOtherPermissions(): void {
    $role = Role::create(['id' => 'content_editor', 'label' => 'Content Editor']);
    $role->grantPermission('access content');
    foreach (self::ARTICLE_PERMISSIONS as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    mukurtu_update_40002();

    $role = Role::load('content_editor');
    $this->assertTrue($role->hasPermission('access content'));
  }

}
