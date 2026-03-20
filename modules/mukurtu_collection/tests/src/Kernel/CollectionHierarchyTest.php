<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\Entity\Node;

/**
 * Tests Collection hierarchy traversal and CollectionHierarchyService.
 *
 * Covers methods not exercised by the existing CollectionEntityTest:
 * getParentCollection(), getParentCollectionId(), getChildCollectionIds(),
 * setChildCollections(), removeAsChildCollection(), and the four public
 * methods on CollectionHierarchyService.
 *
 * @group mukurtu_collection
 */
class CollectionHierarchyTest extends CollectionTestBase {

  /**
   * Root collection (no parent).
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected Collection $root;

  /**
   * Child of $root.
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected Collection $child;

  /**
   * Grandchild of $root (child of $child).
   *
   * @var \Drupal\mukurtu_collection\Entity\Collection
   */
  protected Collection $grandchild;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Build a three-level hierarchy: root → child → grandchild.
    $this->root = $this->buildCollection('Root');
    $this->root->save();

    $this->child = $this->buildCollection('Child');
    $this->child->save();

    $this->grandchild = $this->buildCollection('Grandchild');
    $this->grandchild->save();

    // root → child.
    $this->root->addChildCollection($this->child);
    $this->root->save();

    // child → grandchild.
    $this->child->addChildCollection($this->grandchild);
    $this->child->save();
  }

  // ---------------------------------------------------------------------------
  // Bundle class identity
  // ---------------------------------------------------------------------------

  /**
   * Verifies Node::load() returns a Collection instance for the 'collection'
   * bundle and that it implements the expected interfaces.
   */
  public function testCollectionBundleClass(): void {
    $loaded = Node::load($this->root->id());
    $this->assertInstanceOf(Collection::class, $loaded);
    $this->assertInstanceOf(CollectionInterface::class, $loaded);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $loaded);
  }

  // ---------------------------------------------------------------------------
  // Parent collection queries
  // ---------------------------------------------------------------------------

  /**
   * A root collection (not a child of anything) has no parent.
   */
  public function testGetParentCollection_noParent(): void {
    $this->assertNull($this->root->getParentCollection());
  }

  /**
   * A collection that is a child of another returns the parent entity.
   */
  public function testGetParentCollection_withParent(): void {
    $parent = $this->child->getParentCollection();
    $this->assertNotNull($parent);
    $this->assertEquals($this->root->id(), $parent->id());
  }

  /**
   * getParentCollectionId() returns NULL for a root collection.
   */
  public function testGetParentCollectionId_noParent(): void {
    $this->assertNull($this->root->getParentCollectionId());
  }

  /**
   * getParentCollectionId() returns the parent's node ID.
   */
  public function testGetParentCollectionId_withParent(): void {
    $this->assertEquals($this->root->id(), $this->child->getParentCollectionId());
  }

  /**
   * Grandchild's parent is the child, not the root.
   */
  public function testGetParentCollection_grandchild(): void {
    $parent = $this->grandchild->getParentCollection();
    $this->assertNotNull($parent);
    $this->assertEquals($this->child->id(), $parent->id());
  }

  // ---------------------------------------------------------------------------
  // Child collection ID helpers
  // ---------------------------------------------------------------------------

  /**
   * getChildCollectionIds() returns an array of child node IDs.
   */
  public function testGetChildCollectionIds(): void {
    $ids = $this->root->getChildCollectionIds();
    $this->assertIsArray($ids);
    $this->assertTrue(in_array($this->child->id(), $ids));
  }

  /**
   * getChildCollectionIds() returns an empty array for a leaf collection.
   */
  public function testGetChildCollectionIds_leaf(): void {
    $ids = $this->grandchild->getChildCollectionIds();
    $this->assertIsArray($ids);
    $this->assertEmpty($ids);
  }

  // ---------------------------------------------------------------------------
  // setChildCollections() — bulk replace
  // ---------------------------------------------------------------------------

  /**
   * setChildCollections() replaces the existing child list wholesale.
   */
  public function testSetChildCollections(): void {
    $newChild = $this->buildCollection('New Child');
    $newChild->save();

    $this->root->setChildCollections([$newChild->id()]);
    $this->root->save();

    $ids = $this->root->getChildCollectionIds();
    $this->assertTrue(in_array($newChild->id(), $ids), 'New child is in the list after setChildCollections.');
    $this->assertFalse(in_array($this->child->id(), $ids), 'Old child is no longer in the list.');
  }

  // ---------------------------------------------------------------------------
  // removeAsChildCollection()
  // ---------------------------------------------------------------------------

  /**
   * removeAsChildCollection() removes the entity from its parent's
   * field_child_collections and persists the parent.
   */
  public function testRemoveAsChildCollection(): void {
    // Verify the relationship exists before removal.
    $this->assertTrue(in_array($this->child->id(), $this->root->getChildCollectionIds()));

    $this->child->removeAsChildCollection();

    // Reload the parent from the database to confirm the change was saved.
    /** @var \Drupal\mukurtu_collection\Entity\Collection $reloadedRoot */
    $reloadedRoot = Node::load($this->root->id());
    $this->assertFalse(in_array($this->child->id(), $reloadedRoot->getChildCollectionIds()));
  }

  // ---------------------------------------------------------------------------
  // CollectionHierarchyService::isRootCollection()
  // ---------------------------------------------------------------------------

  /**
   * A collection not referenced as a child of any other is a root.
   */
  public function testIsRootCollection_true(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $this->assertTrue($service->isRootCollection($this->root));
  }

  /**
   * A collection referenced as a child is not a root.
   */
  public function testIsRootCollection_false(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $this->assertFalse($service->isRootCollection($this->child));
    $this->assertFalse($service->isRootCollection($this->grandchild));
  }

  // ---------------------------------------------------------------------------
  // CollectionHierarchyService::getRootCollections()
  // ---------------------------------------------------------------------------

  /**
   * getRootCollections() returns only top-level collections.
   */
  public function testGetRootCollections(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $roots = $service->getRootCollections();

    $rootIds = array_keys($roots);
    $this->assertContains($this->root->id(), $rootIds, 'Root collection is in the root list.');
    $this->assertNotContains($this->child->id(), $rootIds, 'Child collection is not in the root list.');
    $this->assertNotContains($this->grandchild->id(), $rootIds, 'Grandchild collection is not in the root list.');
  }

  /**
   * An isolated collection (no parent, no children) is also a root.
   */
  public function testGetRootCollections_isolated(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');

    $isolated = $this->buildCollection('Isolated');
    $isolated->save();

    $roots = $service->getRootCollections();
    $this->assertArrayHasKey($isolated->id(), $roots);
  }

  // ---------------------------------------------------------------------------
  // CollectionHierarchyService::getRootCollectionForCollection()
  // ---------------------------------------------------------------------------

  /**
   * A root collection is its own root.
   */
  public function testGetRootCollectionForCollection_alreadyRoot(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $found = $service->getRootCollectionForCollection($this->root);
    $this->assertEquals($this->root->id(), $found->id());
  }

  /**
   * Starting from a grandchild traverses all the way to the root.
   */
  public function testGetRootCollectionForCollection_fromGrandchild(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $found = $service->getRootCollectionForCollection($this->grandchild);
    $this->assertEquals($this->root->id(), $found->id());
  }

  /**
   * Starting from a child traverses up to the root.
   */
  public function testGetRootCollectionForCollection_fromChild(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $found = $service->getRootCollectionForCollection($this->child);
    $this->assertEquals($this->root->id(), $found->id());
  }

  // ---------------------------------------------------------------------------
  // CollectionHierarchyService::getCollectionHierarchy()
  // ---------------------------------------------------------------------------

  /**
   * getCollectionHierarchy() returns a nested structure with correct depths.
   */
  public function testGetCollectionHierarchy_structure(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $hierarchy = $service->getCollectionHierarchy($this->root);

    // Root node.
    $this->assertEquals($this->root->id(), $hierarchy['entity']->id());
    $this->assertEquals(0, $hierarchy['depth']);

    // Child node.
    $this->assertCount(1, $hierarchy['children']);
    $childEntry = $hierarchy['children'][0];
    $this->assertEquals($this->child->id(), $childEntry['entity']->id());
    $this->assertEquals(1, $childEntry['depth']);

    // Grandchild node.
    $this->assertCount(1, $childEntry['children']);
    $grandchildEntry = $childEntry['children'][0];
    $this->assertEquals($this->grandchild->id(), $grandchildEntry['entity']->id());
    $this->assertEquals(2, $grandchildEntry['depth']);
  }

  /**
   * max_depth limits how deep the hierarchy is traversed.
   */
  public function testGetCollectionHierarchy_maxDepth(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $hierarchy = $service->getCollectionHierarchy($this->root, 1);

    // Child should appear, but grandchild should not (depth cut at 1).
    $this->assertCount(1, $hierarchy['children']);
    $this->assertEmpty($hierarchy['children'][0]['children']);
  }

  /**
   * A leaf collection returns an empty children array.
   */
  public function testGetCollectionHierarchy_leaf(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $hierarchy = $service->getCollectionHierarchy($this->grandchild);

    $this->assertEquals($this->grandchild->id(), $hierarchy['entity']->id());
    $this->assertEmpty($hierarchy['children']);
  }

  // ---------------------------------------------------------------------------
  // CollectionHierarchyService::getCollectionFromNode()
  // ---------------------------------------------------------------------------

  /**
   * getCollectionFromNode() returns the collection when given a Collection node.
   */
  public function testGetCollectionFromNode_collection(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');
    $result = $service->getCollectionFromNode($this->root);
    $this->assertNotNull($result);
    $this->assertEquals($this->root->id(), $result->id());
  }

  /**
   * getCollectionFromNode() returns NULL when given a non-collection node.
   */
  public function testGetCollectionFromNode_nonCollection(): void {
    /** @var \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $service */
    $service = $this->container->get('mukurtu_collection.hierarchy_service');

    $page = $this->buildItem('A plain page');
    $page->save();

    $this->assertNull($service->getCollectionFromNode($page));
  }

}
