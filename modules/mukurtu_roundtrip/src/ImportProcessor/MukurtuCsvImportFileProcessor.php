<?php

namespace Drupal\mukurtu_roundtrip\ImportProcessor;

use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorInterface;
use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorResult;
use Drupal\file\Entity\File;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Exception;

class MukurtuCsvImportFileProcessor implements MukurtuImportFileProcessorInterface {

  use DependencySerializationTrait;

  protected $fieldMappings;

  public static function getName() {
    return 'Mukurtu Standard CSV Format';
  }

  public static function id() {
    return 'mukurtu_import_standard_csv';
  }

  public function supportsFile(File $file) {
    if ($file->get('filemime')->value === 'text/csv') {
      if ($this->getEntityTypeId($file) !== NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function chunkForBatch(File $file, int $size) {
    return [$file->id()];
  }

  public static function process(File $file, array $context = []) {
    return (new self)->processFile($file, $context);
  }

  protected function processFile(File $file, array $context = []) {
    $fieldResolver = \Drupal::service('mukurtu_roundtrip.import_fieldname_resolver');
    $entityTypeId = $this->getEntityTypeId($file);
    if ($entityTypeId === NULL) {
      // TODO: This should be a custom exception.
      throw new Exception("Could not determine entity type ID");
    }

    // Get the class name for the entity type.
    $entityTypeDefinition = \Drupal::entityTypeManager()->getDefinition($entityTypeId);
    $class = $entityTypeDefinition->getOriginalClass();

    // Parse the CSV.
    $data = file_get_contents($file->getFileUri());
    $csvEncoder = new \Drupal\csv_serialization\Encoder\CsvEncoder;

    // We need to set output_header to FALSE for to get the correct output.
    $csvEncoder->setSettings([
      'delimiter' => ',',
      'enclosure' => '"',
      'escape_char' => "\\",
      'encoding' => 'utf8',
      'strip_tags' => TRUE,
      'trim' => TRUE,
      'output_header' => FALSE,
    ]);
    $rows = $csvEncoder->decode($data, 'csv');

    $keyMapping = $fieldResolver->getEntityBaseKeyMapping($entityTypeId);

    if (!empty($rows[0])) {
      // Convert the base entity fields from labels to fieldnames
      // (e.g., 'Content ID' -> 'nid', 'Content Type' -> 'type').
      foreach ($keyMapping as $mapping) {
        $header_index = array_search($mapping['label'], $rows[0]);
        if ($header_index !== FALSE) {
          // Replace the header label with the actual fieldname.
          $rows[0][$header_index] = $mapping['fieldname'];
        }
      }
    }

    // Convert back to CSV.
    $data = $csvEncoder->encode($rows, 'csv');
    $result = new MukurtuImportFileProcessorResult($data, $class, 'csv', $context);
    return $result;
  }

  /**
   * Get the entity type id from an import file.
   */
  protected function getEntityTypeId($file) {
    $fieldResolver = \Drupal::service('mukurtu_roundtrip.import_fieldname_resolver');
    $data = file_get_contents($file->getFileUri());

    // Parse the CSV.
    $csvEncoder = new \Drupal\csv_serialization\Encoder\CsvEncoder;
    $rows = $csvEncoder->decode($data, 'csv');

    if (empty($rows)) {
      return NULL;
    }

    // Examine headers for entity type info.
    $entityTypeId = $fieldResolver->entityTypeFromFieldLabels($rows[0]);
    if ($entityTypeId !== NULL) {
      return $entityTypeId;
    }

    return NULL;
  }

}
