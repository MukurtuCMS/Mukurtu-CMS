<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\UploadedFileLookupTrait;

/**
 * Mukurtu image item processing plugin.
 *
 * Resolves a cell to an image file ID, from either an existing file ID or the
 * filename of an image uploaded with the import. See
 * UploadedFileLookupTrait::lookUpUploadedFile() for what matches.
 */
#[MigrateProcess('mukurtu_imageitem')]
class ImageItem extends ProcessPluginBase {

  use UploadedFileLookupTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $this->lookUpUploadedFile($value, $this->configuration['upload_location'] ?? NULL);
  }

}
