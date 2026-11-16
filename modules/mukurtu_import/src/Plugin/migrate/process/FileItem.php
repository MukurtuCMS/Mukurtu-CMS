<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_import\UploadedFileLookupTrait;

/**
 * Resolves an incoming file value to a managed file ID.
 *
 * See UploadedFileLookupTrait::lookUpUploadedFile() for what matches.
 */
#[MigrateProcess('mukurtu_fileitem')]
class FileItem extends ProcessPluginBase {

  use UploadedFileLookupTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $this->lookUpUploadedFile($value, $this->configuration['upload_location'] ?? NULL);
  }

}
