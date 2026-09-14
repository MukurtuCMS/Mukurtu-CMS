<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40201(), which revokes Full HTML from authenticated.
 *
 * Note that the format itself has to exist for any of this to be meaningful.
 * "use text format full_html" is a dynamic permission derived from the
 * filter_format entities, and Role::calculateDependencies() silently strips any
 * permission no enabled module declares. Without the format, granting it in
 * setUp would not persist and every assertion below would pass for the wrong
 * reason.
 *
 * @see mukurtu_core_update_40201()
 */
#[Group('mukurtu_core')]
class RevokeAuthenticatedFullHtmlUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['filter']);

    // Both formats, so the test can assert Basic HTML is left alone.
    foreach (['full_html' => 'Full HTML', 'basic_html' => 'Basic HTML'] as $id => $name) {
      if (!FilterFormat::load($id)) {
        FilterFormat::create(['format' => $id, 'name' => $name])->save();
      }
    }

    // Required directly rather than via loadInclude(). mukurtu_core is not
    // enabled here - its dependency chain is far heavier than this hook needs -
    // and loadInclude() can only resolve a module the kernel knows about, so it
    // silently does nothing for a profile-nested module that is switched off.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * Creates a role with the given permissions.
   */
  private function makeRole(string $id, array $permissions): Role {
    $role = Role::create(['id' => $id, 'label' => ucfirst($id)]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();

    return Role::load($id);
  }

  /**
   * The grant is removed from the authenticated role.
   */
  public function testRevokesFullHtmlFromAuthenticated(): void {
    $role = $this->makeRole('authenticated', [
      'use text format basic_html',
      'use text format full_html',
    ]);
    // Precondition: the grant actually persisted, so the format registration
    // above did its job.
    $this->assertTrue($role->hasPermission('use text format full_html'), 'Precondition: the permission did not persist, so this test would pass vacuously.');

    $message = mukurtu_core_update_40201();

    $role = Role::load('authenticated');
    $this->assertFalse($role->hasPermission('use text format full_html'), 'Full HTML is still granted to every logged-in user.');
    $this->assertTrue($role->hasPermission('use text format basic_html'), 'Basic HTML was revoked too; logged-in users could no longer author anything.');
    $this->assertNotNull($message, 'The operator was told nothing about a permission being taken away.');
    $this->assertStringContainsString('Full HTML', (string) $message);
  }

  /**
   * A site that never had the grant is left alone, and says nothing.
   */
  public function testNoOpWhenNotGranted(): void {
    $role = $this->makeRole('authenticated', ['use text format basic_html']);
    $this->assertFalse($role->hasPermission('use text format full_html'));

    $this->assertNull(mukurtu_core_update_40201(), 'An unaffected site should not be told a permission was revoked.');
    $this->assertTrue(Role::load('authenticated')->hasPermission('use text format basic_html'));
  }

  /**
   * Only the authenticated role is touched.
   *
   * Editorial roles are meant to have Full HTML. Revoking it from
   * mukurtu_manager would take away formatting the role is expected to use.
   */
  public function testOtherRolesKeepFullHtml(): void {
    $this->makeRole('authenticated', ['use text format full_html']);
    $this->makeRole('mukurtu_manager', ['use text format full_html']);

    mukurtu_core_update_40201();

    $this->assertFalse(Role::load('authenticated')->hasPermission('use text format full_html'));
    $this->assertTrue(
      Role::load('mukurtu_manager')->hasPermission('use text format full_html'),
      'The manager role lost Full HTML, which it is supposed to have.'
    );
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeRole('authenticated', ['use text format full_html']);

    $first = mukurtu_core_update_40201();
    $second = mukurtu_core_update_40201();

    $this->assertNotNull($first);
    $this->assertNull($second, 'The second run reported a revocation that did not happen.');
    $this->assertFalse(Role::load('authenticated')->hasPermission('use text format full_html'));
  }

  /**
   * A site with no authenticated role at all does not error.
   */
  public function testMissingRoleDoesNotError(): void {
    $this->assertNull(Role::load('authenticated'));
    $this->assertNull(mukurtu_core_update_40201());
  }

}
