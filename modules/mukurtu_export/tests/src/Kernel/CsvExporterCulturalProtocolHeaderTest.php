<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\mukurtu_export\Entity\CsvExporter;

/**
 * Test that the Cultural Protocols field's CSV headers match the format the
 * importer's auto-mapper expects (issue #2029).
 */
class CsvExporterCulturalProtocolHeaderTest extends CsvExportFieldTestBase {

  /**
   * Test the default (unmapped) csv_header_label for a new exporter.
   */
  public function testCulturalProtocolHeaderLabels() {
    $exporter = CsvExporter::create([
      'id' => 'test_header_exporter',
      'label' => 'Test Header Exporter',
    ]);

    $fields = $exporter->getMappedFields('node', 'protocol_aware_content');
    $labels = [];
    foreach ($fields as $field) {
      $labels[$field['field_name']] = $field['csv_header_label'];
    }

    $this->assertEquals('Cultural Protocols > Protocols', $labels['field_cultural_protocols/protocols']);
    $this->assertEquals('Cultural Protocols > Sharing Setting', $labels['field_cultural_protocols/sharing_setting']);
  }

}
