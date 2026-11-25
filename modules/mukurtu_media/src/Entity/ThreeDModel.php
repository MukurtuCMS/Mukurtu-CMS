<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\mukurtu_core\Entity\PeopleTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;

/**
 * Defines the 3D Model media entity bundle class.
 */
class ThreeDModel extends Media implements ThreeDModelInterface, CulturalProtocolControlledInterface, PeopleInterface, MukurtuFilenameGenerationInterface {
  use CulturalProtocolControlledTrait;
  use PeopleTrait;

  /**
   * File extensions accepted for the model itself.
   *
   * The viewer (model-viewer) reads glTF binary only. Adding a format here
   * also needs a viewer that can display it.
   */
  public const MODEL_EXTENSIONS = 'glb';

  /**
   * File extensions accepted for the optional Apple AR version.
   */
  public const AR_EXTENSIONS = 'usdz';

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    $file_settings = [
      'target_type' => 'file',
      'handler' => 'default:file',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'max_filesize' => '',
      'description_field' => FALSE,
      'display_field' => FALSE,
      'display_default' => FALSE,
      'uri_scheme' => 'private',
    ];

    $definitions['field_media_3d_model'] = BaseFieldDefinition::create('file')
      ->setLabel(t('3D Model'))
      ->setDescription(t('Supported file format: glb. <br />Maximum file size: @size.<br />Select "Choose File" to upload a 3D model.', ['@size' => ByteSizeMarkup::create(Environment::getUploadMaxSize())]))
      ->setSettings(['file_extensions' => self::MODEL_EXTENSIONS] + $file_settings)
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_3d_model_usdz'] = BaseFieldDefinition::create('file')
      ->setLabel(t('3D Model for Apple devices'))
      ->setDescription(t('Optional. A USDZ version of the model, so visitors on iPhone and iPad can view it in augmented reality (AR). Supported file format: usdz. <br />Maximum file size: @size.<br />Select "Choose File" to upload a USDZ file.', ['@size' => ByteSizeMarkup::create(Environment::getUploadMaxSize())]))
      ->setSettings(['file_extensions' => self::AR_EXTENSIONS] + $file_settings)
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Tags'))
      ->setDescription(t('Media tags are used to label media assets to help find them within the media library. They are also used to trigger taxonomy based media content warnings. <br />Include as many media tags as needed. Select from existing media tags or add new ones.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'media_tag' => 'media_tag',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => '',
        ],
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_people'] = BaseFieldDefinition::create('mukurtu_entity_reference_role')
      ->setLabel(t('People'))
      ->setDescription(t('A person or people present or referenced in the 3D model. This is used to trigger deceased person media content warnings. <br />Include as many people as needed. Select from existing people or add new ones.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'people' => 'people',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => '',
        ],
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Thumbnail'))
      ->setDescription(t('A thumbnail image is used on browse pages, in carousels, and in the media library. If none is provided, the site\'s default 3D model thumbnail is used. <br />Select "Choose File" to upload a thumbnail image.'))
      ->setSettings([
        'alt_field' => TRUE,
        'alt_field_required' => FALSE,
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
        'target_type' => 'file',
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Identifier'))
      ->setDescription(t('A unique, unambiguous reference to the media asset. Identifiers are often provided by the contributing institution or organization so the original item can be located. Examples include call numbers or accession numbers. Maximum 255 characters.'))
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
   *
   * Used by mukurtu_media_upload_ajax_callback() to fill in the name from
   * the uploaded file's name.
   */
  public function hasUploadedMediaFile() {
    return (bool) ($this->get('field_media_3d_model')->getValue()[0]['fids'][0] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaFilename() {
    $fid = $this->get('field_media_3d_model')->getValue()[0]['fids'][0] ?? NULL;
    $file = $fid ? \Drupal::entityTypeManager()->getStorage('file')->load($fid) : NULL;
    return $file ? $file->getFilename() : NULL;
  }

  /**
   * Returns the file ID of the site's default 3D model thumbnail, if any.
   *
   * Set on the Thumbnail settings form, which stores each media type's
   * default under its bundle ID.
   */
  public function getDefaultThumbnail(): ?int {
    $fid = \Drupal::config('mukurtu_media.thumbnail_settings')->get('3d_model')[0] ?? NULL;
    return $fid ? (int) $fid : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // The server can't draw a picture of a model, so the thumbnail is the
    // one the editor uploaded, else the site default. With neither, the file
    // source's generic icon stays.
    $thumb = $this->get('field_thumbnail')->target_id ?? $this->getDefaultThumbnail();
    if ($thumb) {
      $this->thumbnail->target_id = $thumb;
    }

    // Auto-fill thumbnail alt text from the media name when it is empty.
    $thumbValue = $this->get('field_thumbnail')->getValue();
    if (!empty($thumbValue[0]['target_id']) && empty($thumbValue[0]['alt'])) {
      $this->get('field_thumbnail')->set(0, [
        'target_id' => $thumbValue[0]['target_id'],
        'alt' => $this->label(),
      ]);
    }

    parent::preSave($storage);
  }

}
