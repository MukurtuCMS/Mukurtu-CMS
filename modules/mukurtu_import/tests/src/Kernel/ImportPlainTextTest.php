<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of plain text fields.
 */
class ImportPlainTextTest extends MukurtuImportTestBase {
  protected $node;
  protected $otherUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $node = Node::create([
      'title' => 'Plaintext Title',
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
   * Test changing the title field.
   */
  public function testTitleImport() {
    $new_title = "Brand New Plaintext Title";
    $this->assertNotEquals($this->node->getTitle(), $new_title);

    $data = [
      ['nid', 'title'],
      [$this->node->id(), $new_title],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals($new_title, $updated_node->getTitle());
  }

}
