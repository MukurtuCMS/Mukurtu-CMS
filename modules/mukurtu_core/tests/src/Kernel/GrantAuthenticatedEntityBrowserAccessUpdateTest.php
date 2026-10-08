<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40205(), which backfills entity browser access.
 *
 * Each permission this hook grants is dynamic, defined by
 * \Drupal\entity_browser\Permissions::permissions() from whichever
 * entity_browser config entities exist and have a route. Like the filter
 * format permission in RevokeAuthenticatedFullHtmlUpdateTest, Role::
 * calculateDependencies() silently strips any permission no enabled module
 * declares, so the 9 browsers this hook targets have to actually exist for
 * granting them to mean anything.
 *
 * @see mukurtu_core_update_40205()
 */
#[Group('mukurtu_core')]
class GrantAuthenticatedEntityBrowserAccessUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'entity_browser'];

  /**
   * The 9 permissions the hook is expected to grant.
   */
  private const PERMISSIONS = [
    'access mukurtu_collection_browser entity browser pages',
    'access mukurtu_community_and_protocol_user_browser entity browser pages',
    'access mukurtu_community_select entity browser pages',
    'access mukurtu_content_browser entity browser pages',
    'access mukurtu_dictionary_word_browser entity browser pages',
    'access mukurtu_person_browser entity browser pages',
    'access mukurtu_taxonomy_record_place_term_browser entity browser pages',
    'access mukurtu_taxonomy_record_term_browser entity browser pages',
    'access multipage_item_entity_browser entity browser pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    // Minimal entity_browser entities, one per permission above, just
    // complete enough for EntityBrowser::route() to return a route (a 'modal'
    // display) so Permissions::permissions() actually generates each
    // permission. The real shipped browsers also configure a 'view' widget,
    // but that widget's calculateDependencies() loads the referenced view
    // and calls a method on it unconditionally, so reusing the real config
    // here would fatal on the missing view rather than testing anything.
    foreach (self::PERMISSIONS as $permission) {
      $id = substr($permission, strlen('access '), -strlen(' entity browser pages'));
      EntityBrowser::create([
        'name' => $id,
        'label' => $id,
        'display' => 'modal',
        'widget_selector' => 'single',
        'selection_display' => 'no_display',
        'widgets' => [],
      ])->save();
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
   * All 9 permissions are granted to a role that has none of them.
   */
  public function testGrantsAllPermissionsToAuthenticated(): void {
    $role = $this->makeRole('authenticated', ['access content']);
    foreach (self::PERMISSIONS as $permission) {
      $this->assertFalse($role->hasPermission($permission), "Precondition: authenticated already had '$permission'.");
    }

    $message = mukurtu_core_update_40205();

    $role = Role::load('authenticated');
    foreach (self::PERMISSIONS as $permission) {
      $this->assertTrue($role->hasPermission($permission), "'$permission' was not granted to authenticated.");
    }
    $this->assertNotNull($message, 'The operator was told nothing about permissions being granted.');
  }

  /**
   * A site that already has every permission is left alone, and says nothing.
   */
  public function testNoOpWhenAlreadyGranted(): void {
    $this->makeRole('authenticated', self::PERMISSIONS);

    $this->assertNull(mukurtu_core_update_40205(), 'A site with nothing missing should not be told anything was granted.');
  }

  /**
   * Only the permissions actually missing are granted; the rest are untouched.
   */
  public function testOnlyGrantsWhatIsMissing(): void {
    $already_had = 'access mukurtu_content_browser entity browser pages';
    $role = $this->makeRole('authenticated', [$already_had]);

    $message = mukurtu_core_update_40205();

    $role = Role::load('authenticated');
    foreach (self::PERMISSIONS as $permission) {
      $this->assertTrue($role->hasPermission($permission), "'$permission' was not granted.");
    }
    $this->assertNotNull($message);
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeRole('authenticated', []);

    $first = mukurtu_core_update_40205();
    $second = mukurtu_core_update_40205();

    $this->assertNotNull($first);
    $this->assertNull($second, 'The second run reported a grant that did not happen.');

    $role = Role::load('authenticated');
    foreach (self::PERMISSIONS as $permission) {
      $this->assertTrue($role->hasPermission($permission));
    }
  }

  /**
   * A site with no authenticated role at all does not error.
   */
  public function testMissingRoleDoesNotError(): void {
    $this->assertNull(Role::load('authenticated'));
    $this->assertNull(mukurtu_core_update_40205());
  }

  /**
   * Only the authenticated role is touched.
   */
  public function testOtherRolesAreUntouched(): void {
    $this->makeRole('authenticated', []);
    $this->makeRole('mukurtu_manager', []);

    mukurtu_core_update_40205();

    $manager = Role::load('mukurtu_manager');
    foreach (self::PERMISSIONS as $permission) {
      $this->assertFalse($manager->hasPermission($permission), "mukurtu_manager unexpectedly gained '$permission'.");
    }
  }

}
