<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;

use Drupal\mukurtu_media\Entity\DocumentTextInterface;

class DocumentText extends Media implements DocumentTextInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {

    // only extract PDF text if extracted text field is enabled
    if ($this->hasField("field_extracted_text")) {

      // only consider documents for text extraction, for now
      if ($this->hasField("field_media_document")) {

        // check file MIME type
        $file_type = $this->get('field_media_document')->entity->getMimeType();

        // only proceed if type is application/pdf
        if ($file_type == 'application/pdf') {

          $file_uri = $this->get('field_media_document')->entity->getFileUri();
          $full_path = \Drupal::service('file_system')->realpath($file_uri);

          $cmd = "pdftotext -v";  // initial command to pass to exec()
          $output = [];           // array of strings output of pdftotext call
          $resultCode = -1;       // result code

          // check if pdftotext exists
          exec($cmd, $output, $resultCode);

          if ($resultCode == 127) {
            // pdftotext has not been installed, exit
            return;
          }

          // pdftotext is installed, run exec() with it
          $cmd = "pdftotext " . $full_path . " -";
          $escapedCmd = escapeshellcmd($cmd);

          exec($escapedCmd, $output, $resultCode);

          $extractedText = implode("\n", $output);

          $this->field_extracted_text = $extractedText;
        }
      }
    }
  }
}
