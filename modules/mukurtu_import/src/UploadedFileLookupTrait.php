<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\file\FileInterface;
use Drupal\migrate\MigrateException;

/**
 * Resolves an import cell to a file, by file ID or uploaded filename.
 *
 * Shared by the mukurtu_fileitem and mukurtu_imageitem process plugins.
 */
trait UploadedFileLookupTrait {

  /**
   * Finds the file a cell names.
   *
   * A blank cell, which is what an item without the file exports, names no
   * file. Any other value must be an existing file ID, or the filename of
   * exactly one file in the import's upload location. Otherwise the row fails
   * with a message that says why, rather than importing without the file or
   * failing later with a validation error that doesn't.
   *
   * @param mixed $value
   *   The cell's value.
   * @param string|null $upload_location
   *   The URI that uploaded import files start with.
   *
   * @return int|string|null
   *   The file ID, or NULL for a blank cell.
   *
   * @throws \Drupal\migrate\MigrateException
   *   When no file, or more than one, matches.
   */
  protected function lookUpUploadedFile(mixed $value, ?string $upload_location): int|string|null {
    if ($value === NULL || (is_string($value) && trim($value) === '')) {
      return NULL;
    }

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');

    if (is_numeric($value)) {
      $file = $file_storage->load($value);
      if ($file instanceof FileInterface) {
        return $file->id();
      }
    }

    $ids = $file_storage->getQuery()
      ->condition('filename', $value)
      // The file must be one uploaded for imports.
      ->condition('uri', $upload_location ?? 'private://', 'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();

    if (count($ids) > 1) {
      throw new MigrateException((string) t('More than one uploaded file is named "@name". Remove the extra copies from Media/Binary Files and try again.', ['@name' => $value]));
    }
    $file = $ids ? $file_storage->load(reset($ids)) : NULL;
    if (!$file instanceof FileInterface) {
      throw new MigrateException((string) t('Could not find an uploaded file named "@name".', ['@name' => $value]));
    }
    return $file->id();
  }

}
