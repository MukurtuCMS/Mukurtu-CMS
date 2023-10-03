<?php

namespace Drupal\Tests\mukurtu_collection\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests collection operations & functionality.
 *
 * @group mukurtu_collection
 */
class CollectionEntityTest extends KernelTestBase {

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
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_collection'
  ];

  /**
   * A user to use as the owner for the collection.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * A test collection.
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected $collection;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', 'sequences');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    // Flag community entities as Og groups
    // so Og does its part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create the collection bundle.
    NodeType::create([
      'type' => 'collection',
      'name' => 'Collection',
    ])->save();

    // Create a type to put in the collection.
    NodeType::create([
      'type' => 'thing',
      'name' => 'Thing',
    ])->save();

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('create collection content');
    $role->save();

    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;

    $this->collection = Collection::create([
      'title' => $this->randomString(),
      'type' => 'collection',
      'field_items_in_collection' => [],
      'status' => TRUE,
    ]);
    $this->collection->setOwner($this->owner);
    $this->collection->save();
  }

  /**
   * Test if a collection can contain itself.
   *
   * A collection should not be able to contain itself as an item.
   */
  public function testNoSelfReference() {
    // Try to add the collection to itself as an item. This is not valid.
    $this->collection->add($this->collection);
    $violationList = $this->collection->validate();
    $itemsViolations = $violationList->getByField('field_items_in_collection');
    $violation = $itemsViolations->get(0);
    $this->assertStringContainsString('A collection cannot contain itself', $violation->getMessage());
  }

  /**
   * Test if the collection can have duplicate items.
   *
   * A collection should not be able to have duplicate items.
   */
  public function testNoDuplicates() {
    // Add an item to the collection.
    $thing = Node::create([
      'title' => $this->randomString(),
      'type' => 'thing',
      'status' => TRUE,
      'uid' => 1,
    ]);
    $thing->save();
    $this->collection->add($thing);
    $this->collection->save();

    // Try to add the same item again. This should fail.
    $this->collection->add($thing);
    $violationList = $this->collection->validate();
    $itemsViolations = $violationList->getByField('field_items_in_collection');
    $violation = $itemsViolations->get(0);
    $this->assertStringContainsString('A collection cannot contain duplicates', $violation->getMessage());
  }

}
