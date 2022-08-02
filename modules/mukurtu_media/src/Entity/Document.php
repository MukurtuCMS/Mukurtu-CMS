<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_media\Entity\DocumentInterface;

/**
 * Defines the Document media entity bundle class.
 */
class Document extends Media implements DocumentInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {

    // Only extract PDF text if extracted text field is enabled.
    if ($this->hasField("field_extracted_text")) {

      // Only consider documents for text extraction, for now.
      if ($this->hasField("field_media_document")) {

        // Check file MIME type.
        $file_type = $this->get('field_media_document')->entity->getMimeType();

        // Only proceed if type is application/pdf.
        if ($file_type == 'application/pdf') {

          $file_uri = $this->get('field_media_document')->entity->getFileUri();
          $full_path = \Drupal::service('file_system')->realpath($file_uri);

          // Initial command to pass to exec().
          $cmd = "pdftotext -v";
          // Array of strings output of pdftotext call.
          $output = [];
          $resultCode = -1;

          // Check if pdftotext exists.
          exec($cmd, $output, $resultCode);

          if ($resultCode == 127) {
            // If pdftotext has not been installed, exit.
            return;
          }

          // If pdftotext is installed, run exec() with it.
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
