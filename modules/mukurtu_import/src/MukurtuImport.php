<?php

namespace Drupal\mukurtu_import;

class MukurtuImport {
  protected $metadataFiles;
  protected $binaryFiles;

  public function setMetadataFiles($files) {
    $this->metadataFiles = $files;
  }

  public function setBinaryFiles($files) {
    $this->binaryFiles = $files;
  }

}
