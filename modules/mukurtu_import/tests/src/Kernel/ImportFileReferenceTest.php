<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\node\Entity\Node;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;


/**
 * Test the import of file fields.
 */
class ImportFileReferenceTest extends MukurtuImportTestBase {
  protected $node;
  protected $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => -1,
      'settings' => [
        'target_type'=> 'file',
        'display_field' => FALSE,
        'display_default' => FALSE,
        'uri_scheme' => 'public',
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'File',
      'settings' => [
        'handler' => 'default:file',
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'txt',
        'max_filesize' => '',
        'description_field' => FALSE,
      ],
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

    $file = File::create([
      'uri' => 'https://raw.githubusercontent.com/drupal/drupal/10.2.x/INSTALL.txt',
      'uid' => $this->currentUser->id(),
    ]);
    $file->save();
    $this->file = $file;
  }

  /**
   * Test importing a file reference by ID.
   */
  public function testFileImportById() {
    $data = [
      ['nid', 'Files'],
      [$this->node->id(), $this->file->id()],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_file', 'source' => 'Files'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $refs = $updated_node->get('field_file')->referencedEntities();
    $this->assertCount(1, $refs);
    $this->assertEquals($this->file->id(), $refs[0]->id());
  }

  /**
   * Test importing a file reference by UUID.
   */
  public function testFileImportByUUID() {
    $data = [
      ['nid', 'Files'],
      [$this->node->id(), $this->file->uuid()],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_file', 'source' => 'Files'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $refs = $updated_node->get('field_file')->referencedEntities();
    $this->assertCount(1, $refs);
    $this->assertEquals($this->file->id(), $refs[0]->id());
  }

}
