<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of boolean fields.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_import')]
class ImportBooleanTest extends MukurtuImportTestBase {
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $node = Node::create([
      'title' => 'Boolean Test',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Test importing a 0 or 1.
   */
  public function testZeroAndOne() {
    // 0.
    $data = [
      ['nid', 'status'],
      [$this->node->id(), '0'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'status', 'source' => 'status'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertFalse($updated_node->isPublished());

    // 1.
    $data = [
      ['nid', 'status'],
      [$this->node->id(), '1'],
    ];
    $import_file = $this->createCsvFile($data);
    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node2 = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertTrue($updated_node2->isPublished());
  }

  /**
   * Blank boolean cells on an update keep the stored values, while "0" still
   * sets a field to false.
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
   */
  public function testBlankOnUpdateKeepsExistingValues() {
    $this->node->setUnpublished()->setPromoted(TRUE)->setSticky(TRUE)->save();

    $data = [
      ['nid', 'title', 'status', 'promote', 'sticky'],
      [$this->node->id(), 'Updated With Blank Booleans', '', '', '0'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'status', 'source' => 'status'],
      ['target' => 'promote', 'source' => 'promote'],
      ['target' => 'sticky', 'source' => 'sticky'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->loadUnchanged($this->node->id());
    $this->assertEquals('Updated With Blank Booleans', $updated_node->getTitle());
    $this->assertFalse($updated_node->isPublished());
    $this->assertTrue($updated_node->isPromoted());
    $this->assertFalse($updated_node->isSticky());
  }

  /**
   * Blank boolean cells on new content fall back to the field defaults
   * instead of failing validation.
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2305
   */
  public function testBlankOnNewEntityUsesDefaults() {
    $data = [
      ['Title', 'Protocols', 'Sharing Setting', 'Published', 'Promoted to front page', 'Sticky at top of lists'],
      ['Blank Booleans', (string) $this->protocol->id(), 'any', '', '', ''],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
      ['target' => 'status', 'source' => 'Published'],
      ['target' => 'promote', 'source' => 'Promoted to front page'],
      ['target' => 'sticky', 'source' => 'Sticky at top of lists'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Blank Booleans']);
    $this->assertCount(1, $nodes);
    $new_node = reset($nodes);
    $defaults = Node::create(['type' => 'protocol_aware_content']);
    $this->assertSame($defaults->isPublished(), $new_node->isPublished());
    $this->assertSame($defaults->isPromoted(), $new_node->isPromoted());
    $this->assertSame($defaults->isSticky(), $new_node->isSticky());
  }

}
