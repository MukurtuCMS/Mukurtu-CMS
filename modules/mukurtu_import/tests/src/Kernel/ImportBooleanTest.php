<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of boolean fields.
 */
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
    $this->assertEquals(FALSE, $updated_node->isPublished());

    // 1.
    $data = [
      ['nid', 'status'],
      [$this->node->id(), '1'],
    ];
    $import_file = $this->createCsvFile($data);
    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node2 = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals(TRUE, $updated_node2->isPublished());
  }

}
