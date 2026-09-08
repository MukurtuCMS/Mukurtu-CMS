<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Test the import of timestamp fields.
 */
class ImportTimestampTest extends MukurtuImportTestBase {
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
   * Test importing a timestamp.
   */
  public function testTimestamp() {
    $new_created_time = '1682017200';
    $new_created_time_human_readable = '2023-04-20 19:00:00';
    $data = [
      ['nid', 'created'],
      [$this->node->id(), $new_created_time_human_readable],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'created', 'source' => 'created'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals($new_created_time, $updated_node->getCreatedTime());
  }

  /**
   * Test that importing an update to an existing node bumps "changed".
   *
   * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1574
   */
  public function testChangedTimeAdvancesOnUpdate() {
    $original_changed_time = $this->node->getChangedTime();

    // Guarantee the next request time differs from the original "changed"
    // value so the assertion below is meaningful.
    sleep(1);

    $data = [
      ['nid', 'title'],
      [$this->node->id(), 'Updated via spreadsheet'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals('Updated via spreadsheet', $updated_node->getTitle());
    $this->assertGreaterThan($original_changed_time, $updated_node->getChangedTime());
  }

}
