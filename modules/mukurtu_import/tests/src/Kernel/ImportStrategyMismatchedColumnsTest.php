<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Test importing when the mapping references a column absent from the file.
 *
 * Reproduces #1573: a saved import template can map an entity ID/UUID
 * target to a source column name that isn't actually present in the
 * uploaded file (e.g. a stale template applied without visiting "Customize
 * Settings" first). Before the fix, this caused every row to fail inside
 * the CSV source plugin's Row construction, which the importer swallowed
 * and reported as a success despite nothing being created.
 */
class ImportStrategyMismatchedColumnsTest extends MukurtuImportTestBase {

  /**
   * A mapping that targets nid from a column absent in the uploaded file
   * should not prevent the import from completing and creating the node.
   */
  public function testMismatchedIdColumnFallsBackToRecordNumber() {
    $data = [
      ['title', 'protocols', 'sharing_setting'],
      ['New Node From Mismatched Template', $this->protocol->id(), 'any'],
    ];
    $import_file = $this->createCsvFile($data);

    // The template expects an "ID" column for uniqueness (as shipped
    // templates do), but this particular file has no such column.
    $mapping = [
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing_setting'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['title' => 'New Node From Mismatched Template']);
    $this->assertCount(1, $nodes);
  }

  /**
   * A mapping that targets uuid from a column absent in the uploaded file
   * should likewise fall back to record numbers rather than failing.
   */
  public function testMismatchedUuidColumnFallsBackToRecordNumber() {
    $data = [
      ['title', 'protocols', 'sharing_setting'],
      ['New Node From Mismatched UUID Template', $this->protocol->id(), 'any'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'uuid', 'source' => 'UUID'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing_setting'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['title' => 'New Node From Mismatched UUID Template']);
    $this->assertCount(1, $nodes);
  }

  /**
   * getUnmatchedIdentifierColumns() should report a mapped nid/uuid source,
   * or a configured identifier column, that doesn't exist in the file.
   */
  public function testGetUnmatchedIdentifierColumnsDetectsMismatches() {
    $data = [
      ['title'],
      ['Some Title'],
    ];
    $file = $this->createCsvFile($data);

    // Mapped nid source absent from the file.
    $config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config->setTargetEntityTypeId('node');
    $config->setTargetBundle('protocol_aware_content');
    $config->setMapping([
      ['target' => 'nid', 'source' => 'ID'],
      ['target' => 'title', 'source' => 'title'],
    ]);
    $this->assertEquals(['ID'], $config->getUnmatchedIdentifierColumns($file));

    // A configured identifier column absent from the file.
    $config2 = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config2->setTargetEntityTypeId('node');
    $config2->setTargetBundle('protocol_aware_content');
    $config2->setMapping([
      ['target' => 'title', 'source' => 'title'],
    ]);
    $config2->setConfig('identifier_column', 'Reference Number');
    $this->assertEquals(['Reference Number'], $config2->getUnmatchedIdentifierColumns($file));

    // Everything matches: no unmatched columns.
    $config3 = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config3->setTargetEntityTypeId('node');
    $config3->setTargetBundle('protocol_aware_content');
    $config3->setMapping([
      ['target' => 'nid', 'source' => 'title'],
      ['target' => 'title', 'source' => 'title'],
    ]);
    $this->assertEquals([], $config3->getUnmatchedIdentifierColumns($file));
  }

}
