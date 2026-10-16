<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * A caption or subtitle track (a WebVTT file) for an uploaded video.
 *
 * Extends the file item with the track's language (a Language vocabulary
 * term, whose language code becomes the track's srclang) and its kind. The
 * file_managed foreign key is inherited, so private-file download access still
 * resolves through the referencing media entity.
 */
#[FieldType(
  id: "mukurtu_video_track",
  label: new TranslatableMarkup("Video caption track"),
  description: new TranslatableMarkup("A WebVTT caption or subtitle file with a language and kind."),
  category: "file_upload",
  default_widget: "mukurtu_video_track",
  default_formatter: "file_default",
  list_class: FileFieldItemList::class,
  constraints: ["ReferenceAccess" => [], "FileValidation" => []],
  no_ui: TRUE,
)]
class VideoTrackItem extends FileItem {

  /**
   * Track kinds an author can choose.
   */
  public const KINDS = ['captions', 'subtitles'];

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['language_target_id'] = [
      'description' => 'The ID of the Language taxonomy term for this track.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    $schema['columns']['kind'] = [
      'description' => 'The track kind: captions or subtitles.',
      'type' => 'varchar_ascii',
      'length' => 32,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['language_target_id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Language term ID'));

    $properties['kind'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Kind'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    if (!in_array($this->kind, self::KINDS, TRUE)) {
      $this->kind = 'captions';
    }
    if (empty($this->language_target_id)) {
      $this->language_target_id = NULL;
    }
  }

}
