<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\og\Traits\OgMembershipCreationTrait;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_workflows\Access\ReviewQueueAccessCheck;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests ReviewQueueAccessCheck grants access only to stewards and admins.
 *
 * @group mukurtu_workflows
 */
class ReviewQueueAccessCheckTest extends KernelTestBase {

  use OgMembershipCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'mukurtu_protocol',
    'mukurtu_workflows',
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

  protected ReviewQueueAccessCheck $accessCheck;
  protected Community $community;
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('community');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installEntitySchema('workflow');
    $this->installSchema('system', 'sequences');

    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    Role::create(['id' => 'authenticated', 'label' => 'Authenticated'])->save();

    // Create OG roles so memberships can reference them.
    $this->createOgRole('protocol_steward', 'Protocol Steward');
    $this->createOgRole('language_steward', 'Language Steward');

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

    $this->accessCheck = new ReviewQueueAccessCheck(
      $this->container->get('og.membership_manager')
    );
  }

  /**
   * Creates an OG role on the protocol group.
   */
  protected function createOgRole(string $name, string $label): OgRole {
    $role = OgRole::create([
      'name' => $name,
      'label' => $label,
    ]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
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

  public function testAdminWithBypassNodeAccessIsAllowed(): void {
    $user = $this->createNamedUser('admin');
    $admin_role = Role::create(['id' => 'administrator', 'label' => 'Administrator']);
    $admin_role->grantPermission('bypass node access');
    $admin_role->save();
    $user->addRole('administrator');
    $user->save();

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isAllowed(), 'bypass node access grants access to review queue');
  }

  public function testAdminWithAdministerNodesIsAllowed(): void {
    $user = $this->createNamedUser('content_admin');
    $role = Role::create(['id' => 'content_admin', 'label' => 'Content Admin']);
    $role->grantPermission('administer nodes');
    $role->save();
    $user->addRole('content_admin');
    $user->save();

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isAllowed(), 'administer nodes grants access to review queue');
  }

  public function testProtocolStewardIsAllowed(): void {
    $user = $this->createNamedUser('steward');
    $this->addProtocolMember($user, 'protocol_steward');

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isAllowed(), 'protocol steward can access review queue');
  }

  public function testLanguageStewardIsAllowed(): void {
    $user = $this->createNamedUser('lang_steward');
    $this->addProtocolMember($user, 'language_steward');

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isAllowed(), 'language steward can access review queue');
  }

  public function testAuthenticatedUserWithNoRoleIsDenied(): void {
    $user = $this->createNamedUser('contributor');

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isForbidden(), 'authenticated user with no steward role is denied');
  }

  public function testProtocolMemberWithoutStewardRoleIsDenied(): void {
    $user = $this->createNamedUser('member');
    // Add as a plain member (no steward role).
    $this->protocol->addMember($user);

    $result = $this->accessCheck->access($user);
    $this->assertTrue($result->isForbidden(), 'protocol member without steward role is denied');
  }

}
