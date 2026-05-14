<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\og\Entity\OgRole;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests access to communities.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_protocol')]
class CommunityEntityAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
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
    $this->installEntitySchema('workflow');
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
   * Test access operations for a "Community only" community.
   */
  public function testCommunityOnlyCommunity(): void {
    $this->community->setSharingSetting('community-only');
    $this->community->save();

    // Non-member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertFalse($this->community->access('view', $user));
    $this->assertFalse($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));

    // Member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user);
    $this->assertTrue($this->community->access('view', $user));
    $this->assertFalse($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));

    // Community Manager.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user, ['community_manager']);
    $this->assertTrue($this->community->access('view', $user));
    $this->assertTrue($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));
  }

  /**
   * Test access operations for a public community.
   */
  public function testPublicCommunity(): void {
    $this->community->setSharingSetting('public');
    $this->community->save();

    // Non-member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertTrue($this->community->access('view', $user));
    $this->assertFalse($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));

    // Member.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user);
    $this->assertTrue($this->community->access('view', $user));
    $this->assertFalse($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));

    // Community Manager.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->community->addMember($user, ['community_manager']);
    $this->assertTrue($this->community->access('view', $user));
    $this->assertTrue($this->community->access('update', $user));
    $this->assertFalse($this->community->access('delete', $user));
  }

  /**
   * Test getName().
   */
  public function testGetName(): void
  {
    $name = $this->randomString();
    $testCommunity = Community::create([
      'name' => $name,
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
    $this->assertEquals($name, $testCommunity->getName());
  }

  /**
   * Test setName().
   */
  public function testSetName(): void
  {
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $testCommunity->setName('newName');
    $this->assertEquals('newName', $testCommunity->getName());
  }

  /**
   * Test getDescription().
   */
  public function testGetDescription(): void
  {
    $description = $this->randomString();
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_description' => $description,
    ]);
    $this->assertEquals($description, $testCommunity->getDescription());
  }

  /**
   * Test setDescription().
   */
  public function testSetDescription(): void
  {
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $testCommunity->setDescription('new description');
    $this->assertEquals('new description', $testCommunity->getDescription());
  }

  /**
   * Test getCommunityType().
   *
   * @todo Community::getCommunityType() uses ->value on an entity reference
   *   field, which always returns NULL. It should use ->target_id. Fix in a
   *   separate branch before implementing this test.
   */
  public function testGetCommunityType(): void {
    $this->markTestIncomplete('getCommunityType() returns NULL due to ->value on entity reference field; requires production fix first.');
  }

  /**
   * Test setCommunityType().
   *
   * @todo Blocked by the same getCommunityType() bug — cannot assert the
   *   round-trip until getCommunityType() is fixed to use ->target_id.
   */
  public function testSetCommunityType(): void {
    $this->markTestIncomplete('Blocked by getCommunityType() bug; requires production fix first.');
  }

  /**
   * Test getSharingSetting().
   */
  public function testGetSharingSetting(): void
  {
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_access_mode' => 'public',
    ]);
    $this->assertEquals('public', $testCommunity->getSharingSetting());
  }

  /**
   * Test setSharingSetting().
   */
  public function testSetSharingSetting(): void
  {
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $testCommunity->setSharingSetting('community-only');
    $this->assertEquals('community-only', $testCommunity->getSharingSetting());
  }

  /**
   * Test getCreatedTime().
   */
  public function testGetCreatedTime(): void
  {
    $createdTime = 1677537655;
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'created' => $createdTime,
    ]);

    $this->assertEquals($createdTime, $testCommunity->getCreatedTime());
  }

  /**
   * Test setCreatedTime().
   */
  public function testSetCreatedTime(): void
  {
    $testCommunity = Community::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $createdTime = 1677537655;
    $testCommunity->setCreatedTime($createdTime);
    $this->assertEquals($createdTime, $testCommunity->getCreatedTime());
  }

  /**
   * Test getParentCommunity().
   */
  public function testGetParentCommunity(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test getThumbnailImage().
   */
  public function testGetThumbnailImage(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test setThumbnailImage().
   */
  public function testSetThumbnailImage(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test getBannerImage().
   */
  public function testGetBannerImage(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test setBannerImage().
   */
  public function testSetBannerImage(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test getChildCommunities().
   */
  public function testGetChildCommunities(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test isParentCommunity().
   */
  public function testIsParentCommunity(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test isChildCommunity().
   */
  public function testIsChildCommunity(): void {
    $this->markTestIncomplete('TODO');
  }

  /**
   * Test getProtocols().
   */
  public function testGetProtocols(): void {
    $this->community->setSharingSetting('public');
    $this->community->save();

    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();

    $protocols = $this->community->getProtocols();
    $this->assertCount(1, $protocols);
    $this->assertArrayHasKey($protocol->id(), $protocols);
  }

}
