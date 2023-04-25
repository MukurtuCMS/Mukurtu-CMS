<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_media\Entity\DocumentInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_media\Entity\MukurtuThumbnailGenerationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Defines the Document media entity bundle class.
 */
class Document extends Media implements DocumentInterface, CulturalProtocolControlledInterface, MukurtuThumbnailGenerationInterface {

  use CulturalProtocolControlledTrait;

  protected $fileSystem;

  protected $documentDefaultThumbnail;

  protected function getDefaultThumbnail()
  {
    // Check the config if the user has set a default thumbnail for document.
    // Will remain null if there is no default thumbnail set.
    if (!$this->documentDefaultThumbnail) {
      $config = \Drupal::config('mukurtu_thumbnail.settings');
      if (isset($config->get('document_default_thumbnail')[0])) {
        $this->documentDefaultThumbnail = $config->get('document_default_thumbnail')[0];
      }
    }
    return $this->documentDefaultThumbnail;
  }

  protected function setFileSystem()
  {
    if (!$this->fileSystem) {
      $this->fileSystem = \Drupal::service('file_system');
    }
  }

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    $definitions['field_extracted_text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Extracted Text'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_document'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Document'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setSettings([
        'target_type' => 'file',
        'handler' => 'default:file',
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'txt rtf doc docx ppt pptx xls xlsx pdf odf odg odp ods odt fodt fods fodp fodg key numbers pages',
        'max_filesize' => '',
        'description_field' => FALSE,
        'display_field' => FALSE,
        'display_default' => FALSE,
        'uri_scheme' => 'private',
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => ''
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_people'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('People'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'people' => 'people'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => ''
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Thumbnail'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setSettings([
        'alt_field' => TRUE,
        'alt_field_required' => TRUE,
        'title_field' => FALSE,
        'title_field_required' => FALSE,
        'max_resolution' => '',
        'min_resolution' => '',
        'default_image' => [
          'uuid' => NULL,
          'alt' => '',
          'title' => '',
          'width' => NULL,
          'height' => NULL,
        ],
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'png gif jpg jpeg',
        'max_filesize' => '',
        'handler' => 'default:file',
        'uri_scheme' => 'private',
        'display_field' => FALSE,
        'display_default' => FALSE,
        'target_type' => 'file'
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel('Identifier')
      ->setDescription('')
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

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

    // Set the 'thumbnail' field to our generated thumbnail.
    if (isset($this->get('field_thumbnail')->getValue()[0]['target_id'])) {
      $this->thumbnail->target_id = $this->get('field_thumbnail')->getValue()[0]['target_id'];
    }
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  function generateThumbnail(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($form_state->getValue('field_media_document')[0]["fids"][0])) {
      $docFid = $form_state->getValue('field_media_document')[0]["fids"][0];
    }
    else {
      return null;
    }
    $this->setFileSystem();
    // Load the file.
    $docFile = \Drupal::entityTypeManager()->getStorage('file')->load($docFid);

    // Only attempt to extract the thumbnail if the document is a pdf.
    $docName = $docFile->getFilename();
    $extension = substr($docName, -3);
    if ($extension != 'pdf') {
      return null;
    }

    // Get the document's real path.
    $uri = $docFile->getFileUri();
    $docRealPath = $this->fileSystem->realpath($uri);

    // Compute the thumbnail name without any extensions. This is necessary
    // because pdftoppm does not accept extensions in the destination name.
    // Rather, you specify the extension as an argument and pdftoppm takes care
    // of the rest.
    $docNameNoExtension = substr($docName, 0, strrpos($docName, "."));
    $thumbnailName = $docNameNoExtension . '_thumbnail';

    // The generated thumbnail will be first downloaded to the /tmp directory
    // before it is moved to its permanent location in private://.
    $tmpDir = $this->fileSystem->getTempDirectory();
    $tempThumbnailDest = $tmpDir . "/{$thumbnailName}";

    // Verify that pdftoppm is installed.
    $resultCode = -1;
    $output = [];
    $cmd = "pdftoppm --help";
    $escapedCmd = escapeshellcmd($cmd);
    exec($escapedCmd, $output, $resultCode);
    if ($resultCode == 127) {
      return null;
    }
    // Use pdftoppm to download the first page of the document as a png.
    $cmd = "pdftoppm -singlefile -png '{$docRealPath}' '{$tempThumbnailDest}'";
    $escapedCmd = escapeshellcmd($cmd);
    exec($escapedCmd, $output, $resultCode);
    if ($resultCode == 127) {
      return null;
    }
    // Now that the thumbnail has been downloaded and pdftoppm has given it a
    // .png extension, we must update the name of the thumbnail and the path
    // it was temporarily downloaded to.
    $thumbnailName .= '.png';
    $tempThumbnailDest .= '.png';

    // Compute the permanent thumbnail location. It will be the directory of the
    // document.
    $targetDir = rtrim(str_replace($docName, "", $uri), '/');

    // Move the thumbnail to its permanent location in private://.
    $this->fileSystem->prepareDirectory($targetDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $targetDir . '/' . basename($tempThumbnailDest);
    $this->fileSystem->move($tempThumbnailDest, $destination, FileSystemInterface::EXISTS_REPLACE);

    // Create a File entity for the new thumbnail, passing the thumbnail info.
    $thumbnailFile = File::create([
      'filename' => $thumbnailName,
      'uri' => $targetDir . '/' . $thumbnailName,
      'uid' => 1,
    ]);
    $thumbnailFile->save();

    if ($thumbnailFile->id() == NULL) {
      $defaultThumb = $this->getDefaultThumbnail();
      // If the user has not set a default thumbnail for document, return null.
      return $defaultThumb ? $defaultThumb : null;
    }
    else {
      // Return the new File's id.
      return $thumbnailFile->id();
    }
  }
}
