<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\mukurtu_core\Entity\PeopleTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Defines the Document media entity bundle class.
 */
class Document extends Media implements DocumentInterface, CulturalProtocolControlledInterface, MukurtuThumbnailGenerationInterface, MukurtuFilenameGenerationInterface, PeopleInterface {
  use CulturalProtocolControlledTrait;
  use PeopleTrait;

  /**
   * {@inheritdoc}
   */
  public function getDefaultThumbnail()
  {
    $config = \Drupal::config('mukurtu_thumbnail.settings');
    $defaultDocumentThumbnail = $config->get('document_default_thumbnail')[0] ?? NULL;
    return $defaultDocumentThumbnail;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUploadedMediaFile()
  {
    $fieldMediaValue = $this->get('field_media_document')->getValue()[0]['fids'][0] ?? NULL;
    if ($fieldMediaValue) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaFilename()
  {
    $fid = $this->get('field_media_document')->getValue()[0]['fids'][0] ?? NULL;
    if (!$fid) {
      return NULL;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
    return $file->getFilename();
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
      ->setDescription(t('Supported file formats: pdf, txt, rtf, doc, docx, ppt, pptx, xls, xlsx, odf, odg, odp, ods, odt, fodt, fods, fodp, fodg, key, numbers, pages, csv, sxw, zip, rar, gz, 7z, tar.	</br>Select "Choose File" to upload a document.'))
      ->setDefaultValue('')
      ->setSettings([
        'target_type' => 'file',
        'handler' => 'default:file',
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'txt rtf doc docx ppt pptx xls xlsx pdf odf odg odp ods odt fodt fods fodp fodg key numbers pages csv sxw zip rar gz 7z tar',
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

    $definitions['field_media_tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Tags'))
      ->setDescription(t('Media tags are used to label media assets to help find them within the media library. They are also used to trigger taxonomy based media content warnings. </br>As you type, existing media tags will be displayed. Select an existing media tag or enter a new term. To include additional media tags, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'media_tag' => 'media_tag'
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
      ->setDescription(t('A person or people present or referenced in the document. This is used to trigger deceased person media content warnings.	</br>As you type, names of existing people will be displayed. Select an existing person or enter a new name. To include additional people, select "Add another item".'))
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
      ->setDescription(t('A thumbnail image showing the first page of the document is automatically generated and is normally used, with a generic image used in certain contexts. A thumbnail image does not usually need to be provided.	</br>Select "Choose File" to upload a thumbnail image.'))
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
      ->setDescription('A unique, unambiguous reference to the media asset. Identifiers are often provided by the contributing institution or organization so the original item can be located. Examples include call numbers or accession numbers. Maximum 255 characters.')
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
        $mediaDocument = $this->get('field_media_document');
        $file_type = ($mediaDocument && $mediaDocument->entity) ? $mediaDocument->entity->getMimeType() : NULL;

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
    $defaultThumb = $this->get('field_thumbnail')->getValue()[0]['target_id'] ?? NULL;
    if ($defaultThumb) {
      $this->thumbnail->target_id = $defaultThumb;
    }
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function mediaUploadIsTriggeringElement(FormStateInterface $form_state, $triggeringElementName) {
    $result = FALSE;
    if ($triggeringElementName) {
      $result = preg_match('/field_media_document_\d+_upload_button/', $triggeringElementName) == 1;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  function generateThumbnail(&$element, FormStateInterface $form_state, &$complete_form) {
    $docFid = $form_state->getValue('field_media_document')[0]["fids"][0] ?? NULL;
    if (!$docFid) {
      return NULL;
    }
    $fileSystem = \Drupal::service('file_system');
    // Load the file.
    $docFile = \Drupal::entityTypeManager()->getStorage('file')->load($docFid);

    // Only attempt to extract the thumbnail if the document is a pdf.
    $docName = $docFile->getFilename();
    $extension = substr($docName, -3);
    if ($extension != 'pdf') {
      return NULL;
    }

    // Get the document's real path.
    $uri = $docFile->getFileUri();
    $docRealPath = $fileSystem->realpath($uri);

    // Compute the thumbnail name without any extensions. This is necessary
    // because pdftoppm does not accept extensions in the destination name.
    // Rather, you specify the extension as an argument and pdftoppm takes care
    // of the rest.
    $docNameNoExtension = substr($docName, 0, strrpos($docName, "."));
    $thumbnailName = $docNameNoExtension . '_thumbnail';

    // The generated thumbnail will be first downloaded to the /tmp directory
    // before it is moved to its permanent location in private://.
    $tmpDir = $fileSystem->getTempDirectory();
    $tempThumbnailDest = $tmpDir . "/{$thumbnailName}";

    // Verify that pdftoppm is installed.
    $resultCode = -1;
    $output = [];
    $cmd = "pdftoppm --help";
    $escapedCmd = escapeshellcmd($cmd);
    exec($escapedCmd, $output, $resultCode);
    if ($resultCode == 127) {
      return NULL;
    }
    // Use pdftoppm to download the first page of the document as a png.
    $cmd = "pdftoppm -singlefile -png '{$docRealPath}' '{$tempThumbnailDest}'";
    $escapedCmd = escapeshellcmd($cmd);
    exec($escapedCmd, $output, $resultCode);
    if ($resultCode == 127) {
      return NULL;
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
    $fileSystem->prepareDirectory($targetDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $targetDir . '/' . basename($tempThumbnailDest);
    $fileSystem->move($tempThumbnailDest, $destination, FileSystemInterface::EXISTS_REPLACE);

    // Create a File entity for the new thumbnail, passing the thumbnail info.
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $thumbnailFile = File::create([
      'filename' => $thumbnailName,
      'uri' => $targetDir . '/' . $thumbnailName,
      'uid' => $uid,
    ]);
    $thumbnailFile->save();

    return $thumbnailFile->id() ?? NULL;
  }
}
