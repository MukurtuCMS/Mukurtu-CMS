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

  /**
   * Test importing a file reference by the uploaded file's filename.
   */
  public function testFileImportByFilename() {
    $file = $this->createUploadedFile('public://imports/clip.txt', 'clip.txt');

    $this->importFiles('clip.txt');

    $refs = $this->reloadNode()->get('field_file')->referencedEntities();
    $this->assertCount(1, $refs);
    $this->assertEquals($file->id(), $refs[0]->id());
  }

  /**
   * A filename no uploaded file has fails the row and says so.
   *
   * Before #2697 the row imported without the file, with no message.
   */
  public function testMissingFilenameFailsTheRow() {
    $this->createUploadedFile('public://imports/clip.txt', 'clip.txt');

    $this->importFiles('clip.txt;not-uploaded.txt');

    $this->assertTrue($this->reloadNode()->get('field_file')->isEmpty(), 'A row naming a missing file must not be imported.');
    $this->assertSame(['Could not find an uploaded file named "not-uploaded.txt".'], $this->messages());
  }

  /**
   * A filename that more than one uploaded file has fails the row.
   */
  public function testDuplicateFilenameFailsTheRow() {
    $this->createUploadedFile('public://imports/a/clip.txt', 'clip.txt');
    $this->createUploadedFile('public://imports/b/clip.txt', 'clip.txt');

    $this->importFiles('clip.txt');

    $this->assertTrue($this->reloadNode()->get('field_file')->isEmpty());
    $this->assertSame(['More than one uploaded file is named "clip.txt". Remove the extra copies from Media/Binary Files and try again.'], $this->messages());
  }

  /**
   * A blank cell still means no file, and the row imports.
   */
  public function testBlankCellImportsWithoutFile() {
    $result = $this->importFiles('');

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertTrue($this->reloadNode()->get('field_file')->isEmpty());
    $this->assertSame([], $this->messages());
  }

  /**
   * Imports one row that sets the test node's files.
   *
   * @param string $files
   *   The Files cell.
   *
   * @return int
   *   The import result.
   */
  protected function importFiles(string $files): int {
    $import_file = $this->createCsvFile([
      ['nid', 'Files'],
      [$this->node->id(), $files],
    ]);
    return $this->importCsvFile($import_file, [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_file', 'source' => 'Files'],
    ]);
  }

  /**
   * Creates a file record as an import upload would.
   */
  protected function createUploadedFile(string $uri, string $filename): File {
    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'uid' => $this->currentUser->id(),
    ]);
    $file->save();
    return $file;
  }

  /**
   * Reloads the test node from storage.
   */
  protected function reloadNode(): Node {
    return $this->entityTypeManager->getStorage('node')->loadUnchanged($this->node->id());
  }

  /**
   * Returns the last import's row messages.
   *
   * @return string[]
   *   The messages, without the space the import puts in front of them.
   */
  protected function messages(): array {
    return array_map(
      fn($message) => trim((string) $message->message),
      iterator_to_array($this->lastMigration->getIdMap()->getMessages()),
    );
  }

}
