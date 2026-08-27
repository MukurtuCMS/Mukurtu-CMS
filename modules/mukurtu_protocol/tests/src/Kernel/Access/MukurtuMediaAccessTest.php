<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_protocol\Access\MukurtuPermissionAccessCheck;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the permission string that gates /admin/media (issue #972).
 *
 * Both the entity.media.collection route (mukurtu_media's RouteSubscriber)
 * and the mukurtu_media view's access plugin delegate to
 * MukurtuPermissionAccessCheck::hasMukurtuPermissions() with the same
 * permission string ('site:access media overview+protocol:view media', OR
 * conjunction), so exercising that shared method here covers both entry
 * points.
 *
 * @group mukurtu_protocol
 */
class MukurtuMediaAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * The permission string used by the /admin/media route and view.
   */
  const MEDIA_PERMISSIONS = ['site:access media overview', 'protocol:view media'];

  protected MukurtuPermissionAccessCheck $accessCheck;
  protected Community $community;
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og', 'media']);
    $this->installEntitySchema('community');
    $this->installEntitySchema('media');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workflow');
    $this->installSchema('system', 'sequences');

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    Role::create(['id' => 'authenticated', 'label' => 'Authenticated'])->save();

    $owner = User::create(['name' => 'owner']);
    $owner->save();
    $this->container->get('current_user')->setAccount($owner);

    $this->community = Community::create(['name' => 'Test Community']);
    $this->community->save();
    $this->community->addMember($owner);

    $this->protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [['target_id' => $this->community->id()]],
      'field_sharing_setting' => 'any',
    ]);
    $this->protocol->save();

    $this->accessCheck = new MukurtuPermissionAccessCheck(
      $this->container->get('og.role_manager'),
      $this->container->get('og.membership_manager')
    );
  }

  /**
   * Creates an OG role on the given group bundle.
   */
  protected function createOgRole(string $name, string $label, string $bundle = 'protocol'): OgRole {
    $role = OgRole::create([
      'name' => $name,
      'label' => $label,
    ]);
    $role->setGroupType($bundle);
    $role->setGroupBundle($bundle);
    $role->save();
    return $role;
  }

  /**
   * Creates a saved user with the given display name.
   */
  protected function createNamedUser(string $name): User {
    $user = User::create(['name' => $name]);
    $user->save();
    return $user;
  }

  /**
   * Adds a user to the protocol with the given OG role.
   */
  protected function addProtocolMember(User $user, string $role_name): void {
    $this->protocol->addMember($user);
    $membership = Og::getMembership($this->protocol, $user);
    $membership->addRole(OgRole::getRole('protocol', 'protocol', $role_name));
    $membership->save();
  }

  /**
   * Adds a user to the community with the given OG role.
   */
  protected function addCommunityMember(User $user, string $role_name): void {
    $this->community->addMember($user);
    $membership = Og::getMembership($this->community, $user);
    $membership->addRole(OgRole::getRole('community', 'community', $role_name));
    $membership->save();
  }

  public function testAdministratorBypassIsAllowed(): void {
    $user = $this->createNamedUser('admin');
    $admin_role = Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE]);
    $admin_role->save();
    $user->addRole('administrator');
    $user->save();

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertTrue($result->isAllowed(), 'administrator bypass grants access without an explicit permission');
  }

  public function testSiteRoleWithMediaOverviewPermissionIsAllowed(): void {
    $user = $this->createNamedUser('roundtrip_manager');
    $role = Role::create(['id' => 'mukurtu_roundtrip_manager', 'label' => 'Roundtrip Manager']);
    $role->grantPermission('access media overview');
    $role->save();
    $user->addRole('mukurtu_roundtrip_manager');
    $user->save();

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertTrue($result->isAllowed(), 'a site role granted access media overview is allowed');
  }

  public function testSiteRoleWithoutMediaOverviewPermissionIsDenied(): void {
    $user = $this->createNamedUser('plain_manager');
    $role = Role::create(['id' => 'mukurtu_manager', 'label' => 'Manager']);
    $role->save();
    $user->addRole('mukurtu_manager');
    $user->save();

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertFalse($result->isAllowed(), 'a site role without the permission is not allowed');
  }

  public function testProtocolCuratorIsAllowed(): void {
    $this->createOgRole('protocol-protocol-curator', 'Protocol Curator')->grantPermission('view media')->save();
    $user = $this->createNamedUser('curator');
    $this->addProtocolMember($user, 'protocol-protocol-curator');

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertTrue($result->isAllowed(), 'protocol curator with view media is allowed');
  }

  public function testNewlyGrantedProtocolRoleIsAllowed(): void {
    // Represents one of the five roles migrated by
    // mukurtu_protocol_update_40041() to hold 'view media' directly.
    $this->createOgRole('protocol-protocol-contributor', 'Contributor')->grantPermission('view media')->save();
    $user = $this->createNamedUser('contributor');
    $this->addProtocolMember($user, 'protocol-protocol-contributor');

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertTrue($result->isAllowed(), 'a protocol role granted view media is allowed');
  }

  public function testProtocolRoleWithoutViewMediaIsDenied(): void {
    // Represents a role that was never in the old hardcoded list (e.g.
    // protocol_affiliate) and must stay excluded.
    $this->createOgRole('protocol-protocol-affiliate', 'Affiliate');
    $user = $this->createNamedUser('affiliate');
    $this->addProtocolMember($user, 'protocol-protocol-affiliate');

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertFalse($result->isAllowed(), 'a protocol role without view media is not allowed');
  }

  public function testCustomProtocolRoleWithViewMediaIsAllowed(): void {
    // The actual regression case for issue #972: a brand-new, site-created
    // custom role should gain access purely by being granted the
    // permission, with no code change or role-ID special-casing required.
    $this->createOgRole('protocol-protocol-newly_created_custom_role', 'Custom Role')
      ->grantPermission('view media')
      ->save();
    $user = $this->createNamedUser('custom_role_user');
    $this->addProtocolMember($user, 'protocol-protocol-newly_created_custom_role');

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertTrue($result->isAllowed(), 'a new custom protocol role granted view media is allowed with no special-casing');
  }

  public function testCommunityViewMediaIsNotSufficient(): void {
    // The permission string only checks the 'protocol:' bundle; a
    // community-level grant should not satisfy it (matches the original
    // hardcoded list, which had no community roles).
    $this->createOgRole('community-community-media_viewer', 'Community Media Viewer', 'community')
      ->grantPermission('view media')
      ->save();
    $user = $this->createNamedUser('community_member');
    $this->addCommunityMember($user, 'community-community-media_viewer');

    $result = $this->accessCheck->hasMukurtuPermissions($user, self::MEDIA_PERMISSIONS, 'OR');
    $this->assertFalse($result->isAllowed(), 'a community-level view media grant does not satisfy the protocol-scoped check');
  }

}
