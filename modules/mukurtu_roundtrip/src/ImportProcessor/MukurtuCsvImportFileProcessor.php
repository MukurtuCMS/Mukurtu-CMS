<?php

namespace Drupal\mukurtu_roundtrip\ImportProcessor;

use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorInterface;
use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorResult;
use Drupal\file\Entity\File;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

class MukurtuCsvImportFileProcessor implements MukurtuImportFileProcessorInterface {

  use DependencySerializationTrait;

  public static function getName() {
    return 'Mukurtu Standard CSV Format';
  }

  public static function id() {
    return 'mukurtu_import_standard_csv';
  }

  public function supportsFile(File $file) {
    if ($file->get('filemime')->value === 'text/csv') {
      return TRUE;
    }
    return FALSE;
  }

  public function chunkForBatch(File $file, int $size) {
    return [$file->id()];
  }

  public static function process(File $file, array $context = []) {
    $data = file_get_contents($file->getFileUri());
    $class = 'Drupal\node\Entity\Node';
    $result = new MukurtuImportFileProcessorResult($data, $class, 'csv', $context);
    return $result;
  }

}
