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

    // Match image info in markdown format: ![alt text](image.jpg "title") OR
    // ![alt text](<image id or uuid> "title")
    preg_match('/!\[(.*?)]\s*?\((.+?)\s*(?:"(.+?)")?\)/', $value, $matches, PREG_UNMATCHED_AS_NULL);
    $alt = $matches[1] ?? "";
    $image = $matches[2] ?? $value;
    $title = $matches[3] ?? $alt;

    // Check if incoming value is an existing file ID.
    if (is_numeric($image)) {
      $file = $file_storage->load($image);
      if ($file) {
        return [
          'target_id' => $file->id(),
          'alt' => $alt,
          'title' => $title,
        ];
      }
    }

    // Is this one of the binary files uploaded as part of the import process?
    $query = $file_storage->getQuery();
    $results = $query->condition('filename', $image)
      // The file must be in the provided upload location scope.
      ->condition('uri', $import_uri_base,'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
    if (!empty($results) && count($results) === 1) {
      $file = $file_storage->load(reset($results));
      if ($file instanceof FileInterface) {
        return [
          'target_id' => $file->id(),
          'alt' => $alt,
          'title' => $title,
        ];
      }
    }

    return [];
  }

}
