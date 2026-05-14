<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\mukurtu_multipage_items\Entity\MultipageItem;
use Drupal\mukurtu_multipage_items\MultipageItemInterface;
use Drupal\mukurtu_multipage_items\MultipageItemManager;

/**
 * Tests MultipageItem entity page-management methods and MultipageItemManager.
 *
 * Covers: addPage(), getPages(), hasPage(), getFirstPage(), setFirstPage(),
 * getPages() with access-check filtering, MultipageItemManager::getMultipageEntity(),
 * and MultipageItemManager::isEnabledBundleType().
 *
 * @group mukurtu_multipage_items
 */
class MultipageItemEntityTest extends MultipageItemTestBase {

  // ---------------------------------------------------------------------------
  // Entity class identity
  // ---------------------------------------------------------------------------

  /**
   * MultipageItem::create() returns an entity implementing MultipageItemInterface.
   */
  public function testMultipageItemEntityClass(): void {
    $mpi = $this->buildMultipageItem('Test MPI');
    $mpi->save();

    $loaded = MultipageItem::load($mpi->id());
    $this->assertInstanceOf(MultipageItem::class, $loaded);
    $this->assertInstanceOf(MultipageItemInterface::class, $loaded);
    $this->assertEquals('Test MPI', $loaded->getTitle());
  }

  // ---------------------------------------------------------------------------
  // addPage / getPages / hasPage / getFirstPage — basic operations
  // ---------------------------------------------------------------------------

  /**
   * A freshly created MPI with no pages returns an empty array from getPages().
   */
  public function testGetPages_empty(): void {
    $mpi = $this->buildMultipageItem('Empty MPI');
    $this->assertEmpty($mpi->getPages());
  }

  /**
   * Adding a single page makes it retrievable via getPages().
   */
  public function testAddPage_single(): void {
    $page = $this->buildItem('Page 1');
    $page->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page);

    $pages = $mpi->getPages();
    $this->assertCount(1, $pages);
    $this->assertArrayHasKey($page->id(), $pages);
  }

  /**
   * addPage() appends pages in insertion order.
   */
  public function testAddPage_preservesOrder(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();
    $page3 = $this->buildItem('Page 3');
    $page3->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1)->addPage($page2)->addPage($page3);

    $ids = array_keys($mpi->getPages());
    $this->assertEquals([$page1->id(), $page2->id(), $page3->id()], $ids);
  }

  /**
   * hasPage() returns TRUE for a page that was added.
   */
  public function testHasPage_true(): void {
    $page = $this->buildItem('Page 1');
    $page->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page);

    $this->assertTrue($mpi->hasPage($page));
  }

  /**
   * hasPage() returns FALSE for a node that was never added.
   */
  public function testHasPage_false(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1);

    $this->assertFalse($mpi->hasPage($page2));
  }

  /**
   * getFirstPage() returns NULL when no pages have been added.
   */
  public function testGetFirstPage_empty(): void {
    $mpi = $this->buildMultipageItem('MPI');
    $this->assertNull($mpi->getFirstPage());
  }

  /**
   * getFirstPage() returns the first page added, not subsequent ones.
   */
  public function testGetFirstPage_returnsFirst(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1)->addPage($page2)->save();

    $this->assertEquals($page1->id(), $mpi->getFirstPage()->id());
  }

  // ---------------------------------------------------------------------------
  // setFirstPage
  // ---------------------------------------------------------------------------

  /**
   * setFirstPage() on an empty list makes the node the sole page.
   */
  public function testSetFirstPage_prependsToEmptyList(): void {
    $page = $this->buildItem('Page 1');
    $page->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->setFirstPage($page);

    $this->assertCount(1, $mpi->getPages());
    $this->assertEquals($page->id(), $mpi->getFirstPage()->id());
  }

  /**
   * setFirstPage() with a new node prepends it, leaving existing pages after.
   */
  public function testSetFirstPage_prependsNewNode(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();
    $page3 = $this->buildItem('Page 3');
    $page3->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1)->addPage($page2);
    $mpi->setFirstPage($page3);

    $ids = array_keys($mpi->getPages());
    $this->assertEquals([$page3->id(), $page1->id(), $page2->id()], $ids);
  }

  /**
   * setFirstPage() with a mid-list node moves it to the front without duplicating it.
   */
  public function testSetFirstPage_movesExistingNodeToFront(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();
    $page3 = $this->buildItem('Page 3');
    $page3->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1)->addPage($page2)->addPage($page3);
    $mpi->setFirstPage($page2);

    $ids = array_keys($mpi->getPages());
    // page2 moved to front; page1 and page3 remain in relative order.
    $this->assertEquals([$page2->id(), $page1->id(), $page3->id()], $ids);
    // Exactly three pages — no duplication.
    $this->assertCount(3, $ids);
  }

  /**
   * setFirstPage() when the node is already first leaves the list unchanged
   * and does not create a duplicate entry.
   */
  public function testSetFirstPage_alreadyFirstIsNoOp(): void {
    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page1)->addPage($page2);
    $mpi->setFirstPage($page1);

    $ids = array_keys($mpi->getPages());
    $this->assertEquals([$page1->id(), $page2->id()], $ids);
    $this->assertCount(2, $ids);
  }

  // ---------------------------------------------------------------------------
  // getPages() with access check
  // ---------------------------------------------------------------------------

  /**
   * getPages(FALSE) returns unpublished nodes; getPages(TRUE) excludes them.
   */
  public function testGetPagesWithAccessCheck(): void {
    $published = $this->buildItem('Published Page');
    $published->set('status', TRUE);
    $published->save();

    $unpublished = $this->buildItem('Unpublished Page');
    $unpublished->set('status', FALSE);
    $unpublished->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($published)->addPage($unpublished)->save();

    // Populate node_access grants so accessCheck(TRUE) can resolve correctly.
    node_access_rebuild();

    // Without access check: both pages returned.
    $all = $mpi->getPages(FALSE);
    $this->assertArrayHasKey($published->id(), $all);
    $this->assertArrayHasKey($unpublished->id(), $all);

    // With access check: unpublished filtered out by status = 1 condition.
    $checked = $mpi->getPages(TRUE);
    $this->assertArrayHasKey($published->id(), $checked);
    $this->assertArrayNotHasKey($unpublished->id(), $checked);
  }

  // ---------------------------------------------------------------------------
  // MultipageItemManager::getMultipageEntity()
  // ---------------------------------------------------------------------------

  /**
   * getMultipageEntity() returns the MPI when the node is a direct page of it.
   */
  public function testGetMultipageEntity_found(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');

    $page = $this->buildItem('Page');
    $page->save();

    $mpi = $this->buildMultipageItem('MPI');
    $mpi->addPage($page)->save();

    $found = $manager->getMultipageEntity($page);
    $this->assertNotNull($found);
    $this->assertEquals($mpi->id(), $found->id());
  }

  /**
   * getMultipageEntity() returns NULL when the node is not in any MPI.
   */
  public function testGetMultipageEntity_notFound(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');

    $page = $this->buildItem('Orphan Page');
    $page->save();

    $this->assertNull($manager->getMultipageEntity($page));
  }

  /**
   * getMultipageEntity() returns the correct MPI when multiple MPIs exist.
   */
  public function testGetMultipageEntity_correctMpi(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');

    $page1 = $this->buildItem('Page 1');
    $page1->save();
    $page2 = $this->buildItem('Page 2');
    $page2->save();

    $mpi1 = $this->buildMultipageItem('MPI 1');
    $mpi1->addPage($page1)->save();

    $mpi2 = $this->buildMultipageItem('MPI 2');
    $mpi2->addPage($page2)->save();

    $this->assertEquals($mpi1->id(), $manager->getMultipageEntity($page1)->id());
    $this->assertEquals($mpi2->id(), $manager->getMultipageEntity($page2)->id());
  }

  // ---------------------------------------------------------------------------
  // MultipageItemManager::isEnabledBundleType()
  // ---------------------------------------------------------------------------

  /**
   * isEnabledBundleType() returns TRUE for bundles enabled in config.
   */
  public function testIsEnabledBundleType_enabled(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');
    $this->assertTrue($manager->isEnabledBundleType('digital_heritage')->isEnabled());
  }

  /**
   * isEnabledBundleType() returns FALSE for bundles not enabled in config.
   */
  public function testIsEnabledBundleType_disabled(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');
    $this->assertFalse($manager->isEnabledBundleType('page')->isEnabled());
  }

  /**
   * isEnabledBundleType() returns FALSE for a bundle not mentioned in config at all.
   */
  public function testIsEnabledBundleType_unknown(): void {
    /** @var \Drupal\mukurtu_multipage_items\MultipageItemManager $manager */
    $manager = $this->container->get('mukurtu_multipage_items.multipage_item_manager');
    $this->assertFalse($manager->isEnabledBundleType('nonexistent_bundle')->isEnabled());
  }

}
