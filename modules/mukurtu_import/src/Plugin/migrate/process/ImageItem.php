<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "mukurtu_imageitem"
 * )
 */
class ImageItem extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $importUriBase = $this->configuration['upload_location'] ?? "private://";
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');

    // Match image info in markdown format: ![alt text](image.jpg "title") OR
    // ![alt text](<image id or uuid> "title")
    preg_match('/!\[(.*?)\]\s*?\((.+?)\s*([\"].+[\"])?\)/', $value, $matches, PREG_UNMATCHED_AS_NULL);
    $alt = $matches[1] ?? "";
    // If $title gets a match, it gets double-encapsulated in quotes
    // (e.g. ""title""), so chop off one set of quotes.
    $title = $matches[3] ? substr($matches[3], 1, -1) : $alt;
    $image = $matches[2] ?? $value;

    // Check if incoming value is an existing file ID.
    if (is_numeric($image)) {
      $file = $fileStorage->load($image);
      if ($file) {
        return [
          'target_id' => $file->id(),
          'alt' => $alt,
          'title' => $title,
        ];
      }
    }

    // Is this one of the binary files uploaded as part of the import process?
    $query = $fileStorage->getQuery();
    $results = $query->condition('filename', $image)
      // The file must be in the provided upload location scope.
      ->condition('uri', $importUriBase,'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();
    if (!empty($results) && count($results) == 1) {
      /** @var \Drupal\file\FileInterface $file */
      if ($file = $fileStorage->load(reset($results))) {
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
