<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\media\Entity\Media;
use Drupal\mukurtu_media\Entity\VideoInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\mukurtu_media\Entity\MukurtuThumbnailGenerationInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Defines the Video media entity bundle class.
 */
class Video extends Media implements VideoInterface, CulturalProtocolControlledInterface, MukurtuThumbnailGenerationInterface
{
  use CulturalProtocolControlledTrait;

  /**
   * {@inheritdoc}
   */
  function getDefaultThumbnail() {
    $config = \Drupal::config('mukurtu_thumbnail.settings');
    $defaultVideoThumbnail = $config->get('video_default_thumbnail')[0] ?? NULL;
    return $defaultVideoThumbnail;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUploadedMediaFile() {
    $fieldMediaValue = $this->get('field_media_video_file')->getValue()[0]['fids'][0] ?? NULL;
    if ($fieldMediaValue) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaFilename() {
    $fid = $this->get('field_media_video_file')->getValue()[0]['fids'][0] ?? NULL;
    if (!$fid) {
      return NULL;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
    return $file->getFilename();
  }

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = self::getProtocolFieldDefinitions();

    $definitions['field_media_video_file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Video file'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setSettings([
        'file_extensions' => 'mp4',
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'max_filesize' => '',
        'description_field' => FALSE,
        'handler' => 'default:file',
        'uri_scheme' => 'private',
        'display_field' => FALSE,
        'display_default' => FALSE,
        'target_type' => 'file'
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
  public function preSave(EntityStorageInterface $storage)
  {
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
  function generateThumbnail(&$element, FormStateInterface $form_state, &$complete_form)
  {
    $videoFid = $form_state->getValue('field_media_video_file')[0]["fids"][0] ?? NULL;
    if (!$videoFid) {
      return NULL;
    }
    $fileSystem = \Drupal::service('file_system');

    // Load the file.
    $videoFile = \Drupal::entityTypeManager()->getStorage('file')->load($videoFid);

    // Get the video's real path.
    $uri = $videoFile->getFileUri();
    $mediaRealPath = $fileSystem->realpath($uri);

    // Compute the thumbnail name.
    $filename = $videoFile->getFilename();
    $videoNameNoExt = substr($filename, 0, strrpos($filename, "."));
    $thumbnailName = $videoNameNoExt . "_thumbnail.png";

    // Verify that ffmpeg is installed.
    $resultCode = -1;
    $output = [];
    $cmd = "ffmpeg -version";
    $escapedCmd = escapeshellcmd($cmd);
    exec($escapedCmd, $output, $resultCode);
    if ($resultCode == 127) {
      return NULL;
    }
    // Compute the thumbnail's temporary download location.
    $tmpDir = $fileSystem->getTempDirectory();
    $tempThumbnailDest = $tmpDir . "/{$thumbnailName}";

    // Use ffmpeg to extract the first frame of the video as a screenshot,
    // downloading the resulting thumbnail into the /tmp directory.
    $resultCode = -1;
    $output = [];
    $cmd = "ffmpeg -ss 00:00:00 -i '{$mediaRealPath}' -frames:v 1 '{$tempThumbnailDest}'";
    $escapedCmd = escapeshellcmd($cmd);
    exec($cmd, $output, $resultCode);
    if ($resultCode == 127) {
      return NULL;
    }
    // Compute the permanent thumbnail location. It will be the directory of the
    // video.
    $targetDir = rtrim(str_replace($filename, "", $uri), '/');

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
