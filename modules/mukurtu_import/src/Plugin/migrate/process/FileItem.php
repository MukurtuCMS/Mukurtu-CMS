<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "mukurtu_fileitem"
 * )
 */
class FileItem extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $importUriBase = $this->configuration['upload_location'] ?? "private://";
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');

    // Check if incoming value is an existing file ID.
    if (is_numeric($value)) {
      $file = $fileStorage->load($value);
      if ($file) {
        return $file->id();
      }
    }

    // Is this one of the binary files uploaded as part of the import process?
    $query = $fileStorage->getQuery();
    $results = $query->condition('filename', $value)
      // The file must be in the provided upload location scope.
      ->condition('uri', $importUriBase, 'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
    if (!empty($results) && count($results) == 1) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $fileStorage->load(reset($results))) {
        return $file->id();
      }
    }
  }

}
