<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the ability of import to lookup entities by different ID types.
 */
class ImportEntityLookupTest extends MukurtuImportTestBase {
  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Test if we can update an existing node by using its ID.
   */
  public function testUpdateById() {
    $node = Node::create([
      'title' => 'Before Update',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    // Import test data.
    $data = [
      ['nid', 'title'],
      [$node->id(), 'Found by NID'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $node = $this->entityTypeManager->getStorage('node')->load($node->id());
    $this->assertEquals('Found by NID', $node->getTitle());
  }

  /**
   * Test if we can update an existing node by using its UUID.
   */
  public function testUpdateByUUID() {
    $node = Node::create([
      'title' => 'Before Update',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    // Import test data.
    $data = [
      ['uuid', 'title'],
      [$node->uuid(), 'Found by UUID'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'uuid', 'source' => 'uuid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $node = $this->entityTypeManager->getStorage('node')->load($node->id());
    $this->assertEquals('Found by UUID', $node->getTitle());
  }
}
