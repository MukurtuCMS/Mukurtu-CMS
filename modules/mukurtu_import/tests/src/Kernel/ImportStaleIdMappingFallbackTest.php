<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\file\Entity\File;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests that a mapping referencing an id/uuid/label column not actually
 * present in the uploaded file falls back to the next available row
 * identifier, instead of aborting the whole migration.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154
 */
class ImportStaleIdMappingFallbackTest extends MukurtuImportTestBase {

  /**
   * A mapping that includes an "ID" column not present in the file falls
   * back to _record_number instead of failing the whole migration.
   */
  public function testMissingIdColumnFallsBackToRecordNumber(): void {
    $data = [
      ['title', 'protocols', 'sharing_setting'],
      ['Collection Test', $this->protocol->id(), 'any'],
    ];
    $import_file = $this->createCsvFile($data);

    // Mirrors the shipped "* - all fields" templates: an ID -> nid mapping
    // is present even though this minimal file has no ID column.
    $mapping = [
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing_setting'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['title' => 'Collection Test']);
    $this->assertCount(1, $nodes);
  }

  /**
   * When the file genuinely includes the mapped ID column, it is still
   * used as the row identifier (regression guard for update-by-ID imports).
   */
  public function testExistingIdColumnIsStillUsedAsIdentifier(): void {
    $node = Node::create([
      'title' => 'Original Title',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $data = [
      ['ID', 'title'],
      [$node->id(), 'Updated Title'],
    ];
    $import_file = $this->createCsvFile($data);
    $mapping = [
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $updated = $this->entityTypeManager->getStorage('node')->load($node->id());
    $this->assertEquals('Updated Title', $updated->label());
    $this->assertCount(1, $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'protocol_aware_content']));
  }

  /**
   * The same fallback applies to taxonomy term imports mapping a "Term ID"
   * column that isn't actually present in the file.
   */
  public function testMissingTermIdColumnFallsBackToRecordNumber(): void {
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();

    $data = [
      ['name'],
      ['Keyword Test'],
    ];
    $import_file = $this->createCsvFile($data);

    // Mirrors the shipped "taxonomy_*_all_fields" templates: a Term ID ->
    // tid mapping is present even though this minimal file has no Term ID
    // column.
    $mapping = [
      ['target' => 'tid', 'source' => 'Term ID'],
      ['target' => 'name', 'source' => 'name'],
    ];

    $result = $this->importCsvFile($import_file, $mapping, 'taxonomy_term', 'keywords');

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['name' => 'Keyword Test']);
    $this->assertCount(1, $terms);
  }

  /**
   * getLabelSourceColumn() ignores a mapped label column that isn't
   * actually a header in the given file, but still returns it when no
   * file is given (e.g. template-editing forms with no file context).
   */
  public function testGetLabelSourceColumnValidatesAgainstFileHeaders(): void {
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping([
      ['target' => 'title', 'source' => 'Title'],
    ]);

    // No file given: unvalidated, returns the stored mapping as-is.
    $this->assertEquals('Title', $import_config->getLabelSourceColumn());

    // File without a "Title" header: the stale mapping is ignored.
    $file_without_title = $this->createCsvFile([['name'], ['A Name']]);
    $this->assertNull($import_config->getLabelSourceColumn($file_without_title));

    // File that actually has the "Title" header: still returned.
    $file_with_title = $this->createCsvFile([['Title'], ['A Title']]);
    $this->assertEquals('Title', $import_config->getLabelSourceColumn($file_with_title));

    // A file whose headers can't be read at all (e.g. missing/unreadable)
    // must still be treated as "column absent", not fall through to the
    // unvalidated behavior -- getCSVHeaders() returns [] on read failure,
    // which is falsy just like "no file given" and must not be conflated
    // with it.
    $unreadable_file = File::create(['uri' => 'public://does-not-exist.csv', 'status' => 1]);
    $unreadable_file->save();
    $this->assertNull($import_config->getLabelSourceColumn($unreadable_file));
  }

}
