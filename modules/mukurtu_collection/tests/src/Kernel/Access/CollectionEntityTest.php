<?php

namespace Drupal\Tests\mukurtu_collection\Kernel\Access;

use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

/**
 * Tests collection operations & functionality.
 *
 * @group mukurtu_collection
 */
class CollectionEntityTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected $protocol;
  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the collection bundle.
    NodeType::create([
      'type' => 'collection',
      'name' => 'Collection',
    ])->save();

    $community = Community::create([
      'name' => 'Community',
    ]);
    $community->save();
    $this->community = $community;
    $this->community->addMember($this->currentUser);

    $protocol = Protocol::create([
      'name' => "Protocol",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($this->currentUser, ['protocol_steward']);
    $this->protocol = $protocol;

    $collection = $this->createEmptyCollection();
    $collection->setSharingSetting('any');
    $collection->setProtocols([$protocol]);
    $collection->save();
    $this->collection = $collection;
  }

  /**
   * Create an empty collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   */
  protected function createEmptyCollection() : Collection {
    return Node::create([
      'title' => $this->randomString(),
      'type' => 'collection',
      'field_items_in_collection' => [],
      'field_keywords' => NULL,
      'field_child_collections' => NULL,
      'field_collection_image' => NULL,
      'field_related_content' => NULL,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
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
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $thing->save();
    $this->assertEquals(0, $this->collection->getCount());
    $this->collection->add($thing);
    $this->collection->save();
    $this->assertEquals(1, $this->collection->getCount());

    // Try to add the same item again. This should fail.
    $this->collection->add($thing);
    $violationList = $this->collection->validate();
    $itemsViolations = $violationList->getByField('field_items_in_collection');
    $violation = $itemsViolations->get(0);
    $this->assertStringContainsString('A collection cannot contain duplicates', $violation->getMessage());
  }

  /**
   * Test to check that a collection cannot contain itself as a sub collection.
   */
   public function testSubCollectionNoSelfReference() {
    $this->collection->addChildCollection($this->collection);
    //$this->collection->set('field_child_collections', [$this->collection->id()]);
    $violationList = $this->collection->validate();
    //$this->debugValidationList($violationList);
    $this->assertEquals(1, $violationList->count());
    $itemsViolations = $violationList->getByField('field_child_collections');
    $this->assertCount(1, $itemsViolations);
    $violation = $itemsViolations->get(0);
    $this->assertStringContainsString('A collection cannot be its own sub-collection', $violation->getMessage());
  }

  /**
   * Test valid sub collection configurations.
   */
  public function testValidSubCollection() {
    $subCollection = $this->createEmptyCollection();
    $subCollection->setProtocols([$this->protocol]);
    $subCollection->setSharingSetting('any');
    $subCollection->save();

    $parentCollection = $this->createEmptyCollection();
    $parentCollection->setProtocols([$this->protocol]);
    $parentCollection->setSharingSetting('any');
    $parentCollection->addChildCollection($subCollection);

    // Add a child collection.
    $violationList = $parentCollection->validate();
    $this->assertEquals(0, $violationList->count());
    $parentCollection->save();
    $this->assertCount(1, $parentCollection->get('field_child_collections')->getValue());

    // Subcollection cannot be added to collection because it is already
    // attached to parentCollection.
    $this->collection->addChildCollection($subCollection);
    $violationList = $this->collection->validate();
    $this->assertEquals(1, $violationList->count());
    $itemsViolations = $violationList->getByField('field_child_collections');
    $violation = $itemsViolations->get(0);
    $this->assertStringContainsString('is already part of a collection hierarchy and cannot be used in another', $violation->getMessage());

    // parentCollection can be added as a subCollection.
    $this->collection->set('field_child_collections', []);
    $this->collection->addChildCollection($parentCollection);
    $violationList = $this->collection->validate();
    $this->assertEquals(0, $violationList->count());
  }


  /**
   * Test getCount for single level collections.
   */
  public function testSingleCollectionItemCounts() {
    $things = [];
    foreach (range(0,2) as $delta) {
      $thing = Node::create([
        'title' => $this->randomString(),
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $thing->save();
      $things[$thing->id()] = $thing;
    }

    $this->assertEquals(0, $this->collection->getCount());

    $expectedCount = 1;
    foreach ($things as $thing) {
      $this->collection->add($thing);
      $this->collection->save();
      $this->assertEquals($expectedCount, $this->collection->getCount());

      $expectedCount += 1;
    }

    $expectedCount = 2;
    foreach ($things as $thing) {
      $this->collection->remove($thing);
      $this->collection->save();
      $this->assertEquals($expectedCount, $this->collection->getCount());
      $expectedCount -= 1;
    }
  }

  /**
   * Test getChildCollections.
   */
  public function testGetChildCollections() {
    $subCollection1 = $this->createEmptyCollection();
    $subCollection1->setProtocols([$this->protocol]);
    $subCollection1->setSharingSetting('any');
    $subCollection1->save();

    $subCollection2 = $this->createEmptyCollection();
    $subCollection2->setProtocols([$this->protocol]);
    $subCollection2->setSharingSetting('any');
    $subCollection2->save();

    $children_ids = [$subCollection1->id(), $subCollection2->id()];

    $this->collection->addChildCollection($subCollection1);
    $this->collection->addChildCollection($subCollection2);
    $children = $this->collection->getChildCollections();
    $this->assertCount(2, $children);
    foreach ($children as $child) {
      $this->assertEquals('node', $child->getEntityTypeId());
      $this->assertEquals('collection', $child->bundle());
      $this->assertTrue(in_array($child->id(), $children_ids));
    }
  }

  /**
   * Test that a collection can have multiple items.
   */
  public function testMultipleItemsInCollection() {
    $things = [];
    foreach (range(0, 4) as $delta) {
      $thing = Node::create([
        'title' => $this->randomString(),
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $thing->save();
      $things[$thing->id()] = $thing;
    }

    $this->collection->set('field_items_in_collection', array_keys($things));
    $this->collection->save();
    $this->assertEquals(5, $this->collection->getCount());
  }

  /**
   * Test getCount for a collection with sub-collections.
   */
  public function testCollectionWithSubcollectionsItemCounts() {
    $things = [];
    foreach (range(0, 4) as $delta) {
      $thing = Node::create([
        'title' => $this->randomString(),
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $thing->save();
      $things[$thing->id()] = $thing;
    }


    $subCollection1 = $this->createEmptyCollection();
    $subCollection1->setProtocols([$this->protocol]);
    $subCollection1->setSharingSetting('any');
    $subCollection1->set('field_items_in_collection', array_keys($things));
    $subCollection1->save();
    $this->assertEquals(5, $subCollection1->getCount());

    $subCollection2 = $this->createEmptyCollection();
    $subCollection2->setProtocols([$this->protocol]);
    $subCollection2->setSharingSetting('any');
    $subCollection2->set('field_items_in_collection', array_slice(array_keys($things), 2));
    $subCollection2->save();
    $this->assertEquals(3, $subCollection2->getCount());

    $middleParentCollection = $this->createEmptyCollection();
    $middleParentCollection->setProtocols([$this->protocol]);
    $middleParentCollection->setSharingSetting('any');
    $middleParentCollection->save();
    $this->assertEquals(0, $middleParentCollection->getCount());

    $middleParentCollection->addChildCollection($subCollection1);
    $middleParentCollection->addChildCollection($subCollection2);
    $middleParentCollection->save();
    $this->assertEquals(8, $middleParentCollection->getCount());

    $middleParentCollection->add(reset($things));
    $middleParentCollection->save();
    $this->assertEquals(9, $middleParentCollection->getCount());

    $this->collection->addChildCollection($middleParentCollection);
    $this->assertEquals(9, $this->collection->getCount());
  }

}
