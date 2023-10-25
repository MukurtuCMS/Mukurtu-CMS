<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;


/**
 * Test the import of original date fields.
 */
class ImportOriginalDateTest extends MukurtuImportTestBase {
  protected $node;

  protected static $modules = ['original_date'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'type' => 'original_date',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Original Date',
    ])->save();

    $node = Node::create([
      'title' => 'Title',
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
   * Test importing original dates.
   */
  public function testOriginalDateImport() {
    $data = [
      ['nid', 'Original Date'],
      [$this->node->id(), "1999;1979-08;1234-01-02"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_original_date', 'source' => 'Original Date'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $dates = $updated_node->get('field_original_date')->getValue();

    $this->assertCount(3, $dates);
    $this->assertEquals('1999', $dates[0]['date']);
    $this->assertEquals('1999', $dates[0]['year']);
    $this->assertEquals('', $dates[0]['month']);
    $this->assertEquals('', $dates[0]['day']);

    $this->assertEquals('1979-08', $dates[1]['date']);
    $this->assertEquals('1979', $dates[1]['year']);
    $this->assertEquals('08', $dates[1]['month']);
    $this->assertEquals('', $dates[1]['day']);

    $this->assertEquals('1234-01-02', $dates[2]['date']);
    $this->assertEquals('1234', $dates[2]['year']);
    $this->assertEquals('01', $dates[2]['month']);
    $this->assertEquals('02', $dates[2]['day']);
  }

}
