<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\file\FileInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Mukurtu image item processing plugin.
 */
#[MigrateProcess('mukurtu_imageitem')]
class ImageItem extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $import_uri_base = $this->configuration['upload_location'] ?? "private://";
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');

    // Check if incoming value is an existing file ID.
    if (is_numeric($value)) {
      $file = $file_storage->load($value);
      if ($file instanceof FileInterface) {
        return $file->id();
      }
    }

    // Is this one of the binary files uploaded as part of the import process?
    $query = $file_storage->getQuery();
    $results = $query->condition('filename', $value)
      // The file must be in the provided upload location scope.
      ->condition('uri', $import_uri_base,'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
    if (!empty($results) && count($results) === 1) {
      $file = $file_storage->load(reset($results));
      if ($file instanceof FileInterface) {
        return $file->id();
      }
    }

    return [];
  }

}
