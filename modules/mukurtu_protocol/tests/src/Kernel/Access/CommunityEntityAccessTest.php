<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\og\Entity\OgRole;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests access to communities.
 *
 * @group mukurtu_protocol
 */
class CommunityEntityAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'mukurtu_protocol',
  ];

  /**
   * A user not involved in testing to use as the owner for content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * Test Community.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('system', 'sequences');

    // Flag community entities as Og groups
    // so Og does its part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('view published community entities');
    $role->save();

    // Create the community manager role with our
    // default Mukurtu permission set.
    $values = [
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => [
        'update group',
        'approve and deny subscription',
        'add user',
        'manage members',
        'create protocol protocol',
        'delete any protocol protocol',
        'delete own protocol protocol',
        'update any protocol protocol',
        'update own protocol protocol',
      ],
    ];
    $communityManagerRole = OgRole::create($values);
    $communityManagerRole->setGroupType('community');
    $communityManagerRole->setGroupBundle('community');
    $communityManagerRole->save();

    // User to own content in tests where the tested user shouldn't
    // be the owner.
    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;

    $this->community = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
  }

  /**
   * Test access operations for a strict community.
   */
  public function testStrictCommunity() {
    $this->community->setSharingSetting('strict');
    $this->community->save();

    // Non-member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertEquals(FALSE, $this->community->access('view', $user));
    $this->assertEquals(FALSE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));

    // Member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user);
    $this->assertEquals(TRUE, $this->community->access('view', $user));
    $this->assertEquals(FALSE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));

    // Community Manager.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user, ['community_manager']);
    $this->assertEquals(TRUE, $this->community->access('view', $user));
    $this->assertEquals(TRUE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));
  }

  /**
   * Test access operations for an open community.
   */
  public function testOpenCommunity() {
    $this->community->setSharingSetting('open');
    $this->community->save();

    // Non-member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertEquals(TRUE, $this->community->access('view', $user));
    $this->assertEquals(FALSE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));

    // Member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user);
    $this->assertEquals(TRUE, $this->community->access('view', $user));
    $this->assertEquals(FALSE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));

    // Community Manager.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user, ['community_manager']);
    $this->assertEquals(TRUE, $this->community->access('view', $user));
    $this->assertEquals(TRUE, $this->community->access('update', $user));
    $this->assertEquals(FALSE, $this->community->access('delete', $user));
  }

}
