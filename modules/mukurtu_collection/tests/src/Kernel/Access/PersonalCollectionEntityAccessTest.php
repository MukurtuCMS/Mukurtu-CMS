<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\og\Entity\OgRole;
use Drupal\mukurtu_collection\Entity\PersonalCollection;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests access to personal collections.
 *
 * @group mukurtu_collection
 */
class PersonalCollectionEntityAccessTest extends KernelTestBase
{

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
    'mukurtu_collection'
  ];

  /**
   * A user to use as the owner for the personal collection.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * Test Personal Collection.
   *
   * @var \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   */
  protected $personalCollection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('personal_collection');
    $this->installSchema('system', 'sequences');


    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('view published personal collection entities');
    $role->save();

    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;

    $this->personalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
    ]);
    $this->personalCollection->setOwner($this->owner);
  }

  /**
   * Test access operations for a public personal collection.
   */
  public function testPublicPersonalCollection()
  {
    $this->personalCollection->setPrivacy('public');
    $this->personalCollection->save();

    // Non-owner.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertEquals(TRUE, $this->personalCollection->access('view', $user));
    $this->assertEquals(FALSE, $this->personalCollection->access('update', $user));
    $this->assertEquals(FALSE, $this->personalCollection->access('delete', $user));

    // Owner.
    $this->assertEquals(TRUE, $this->personalCollection->access('view', $this->owner));
    $this->assertEquals(TRUE, $this->personalCollection->access('update', $this->owner));
    $this->assertEquals(TRUE, $this->personalCollection->access('delete', $this->owner));
  }

  /**
   * Test access operations for a private personal collection.
   */
  public function testPrivatePersonalCollection()
  {
    $this->personalCollection->setPrivacy('private');
    $this->personalCollection->save();

    // Non-owner.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->assertEquals(FALSE, $this->personalCollection->access('view', $user));
    $this->assertEquals(FALSE, $this->personalCollection->access('update', $user));
    $this->assertEquals(FALSE, $this->personalCollection->access('delete', $user));

    // Owner.
    $this->assertEquals(TRUE, $this->personalCollection->access('view', $this->owner));
    $this->assertEquals(TRUE, $this->personalCollection->access('update', $this->owner));
    $this->assertEquals(TRUE, $this->personalCollection->access('delete', $this->owner));
  }

  /**
   * Test getName().
   */
  public function testGetName() {
    $collectionName = $this->randomString();
    $testPersonalCollection = PersonalCollection::create([
      'name' => $collectionName,
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
    $this->assertEquals($collectionName, $testPersonalCollection->getName());
  }

  /**
   * Test setName().
   */
  public function testSetName()
  {
    $testPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $testPersonalCollection->setName('newName');
    $this->assertEquals('newName', $testPersonalCollection->getName());
  }

  /**
   * Test getCreatedTime().
   */
  public function testGetCreatedTime()
  {
    $createdTime = 1677537655;
    $testPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'created' => $createdTime
    ]);

    $this->assertEquals($createdTime, $testPersonalCollection->getCreatedTime());
  }

  /**
   * Test setCreatedTime().
   */
  public function testSetCreatedTime()
  {
    $testPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $createdTime = 1677537655;
    $testPersonalCollection->setCreatedTime($createdTime);
    $this->assertEquals($createdTime, $testPersonalCollection->getCreatedTime());
  }

  /**
   * Test getPrivacy().
   */
  public function testGetPrivacy()
  {
    $testPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_pc_privacy' => 'public',
    ]);

    $this->assertEquals('public', $testPersonalCollection->getPrivacy());
  }

  /**
   * Test setPrivacy().
   */
  public function testSetPrivacy()
  {
    $testPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

    $testPersonalCollection->setPrivacy('private');
    $this->assertEquals('private', $testPersonalCollection->getPrivacy());
  }

  /**
   * Test isPrivate().
   */
  public function testIsPrivate()
  {
    $publicPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_pc_privacy' => 'public',
    ]);

    $privatePersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_pc_privacy' => 'private',
    ]);

    $this->assertEquals(FALSE, $publicPersonalCollection->isPrivate());
    $this->assertEquals(TRUE, $privatePersonalCollection->isPrivate());
  }
}
