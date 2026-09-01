<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use Drupal\mukurtu_export\Entity\ExportList;
use Drupal\mukurtu_export\ExportItemIdentity;
use Drupal\mukurtu_export\ExportListSource;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ExportList's item_languages field and ExportListSource's key
 * building (#1260 Phase 5 export track).
 */
#[Group('mukurtu_export')]
class ExportListItemLanguageTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_export',
    'mukurtu_multipage_items',
  ];

  protected ExportList $list;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('export_list');

    $this->list = ExportList::create([
      'label' => 'Test List',
      'uid' => $this->currentUser->id(),
    ]);
    $this->list->setItems(['node' => [1 => 1, 2 => 2]]);
    $this->list->save();
  }

  /**
   * An item with no recorded language exports with a bare-ID key -
   * unchanged from before this field existed.
   */
  public function testUntaggedItemUsesBareKey(): void {
    $this->assertNull($this->list->getItemLanguage('node', 1));

    $entities = (new ExportListSource($this->list))->getEntities();
    $this->assertArrayHasKey('1', $entities['node']);
    $this->assertArrayNotHasKey('1:es', $entities['node']);
  }

  /**
   * Setting an item's language produces a composite key for that item
   * only - other items in the same list are unaffected.
   */
  public function testTaggedItemUsesCompositeKey(): void {
    $this->list->setItemLanguage('node', 1, 'es')->save();

    $this->assertSame('es', $this->list->getItemLanguage('node', 1));
    $this->assertNull($this->list->getItemLanguage('node', 2));

    $entities = (new ExportListSource($this->list))->getEntities();
    $expectedKey = ExportItemIdentity::encode(1, 'es');
    $this->assertArrayHasKey($expectedKey, $entities['node']);
    $this->assertArrayHasKey('2', $entities['node']);
  }

  /**
   * Clearing a previously set language reverts the item to a bare key.
   */
  public function testClearingLanguageReturnsToDefault(): void {
    $this->list->setItemLanguage('node', 1, 'es')->save();
    $this->list->setItemLanguage('node', 1, NULL)->save();

    $this->assertNull($this->list->getItemLanguage('node', 1));

    $entities = (new ExportListSource($this->list))->getEntities();
    $this->assertArrayHasKey('1', $entities['node']);
  }

}
