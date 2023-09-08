<?php

namespace Drupal\mukurtu_core\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * External embed entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "external_embed",
 *   label = @Translation("External Embed"),
 *   description = @Translation("External embed code."),
 *   allowed_field_types = {"text_long"},
 * )
 */
class ExternalEmbed extends MediaSourceBase
{
  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes()
  {
    return [
      'embed_code' => $this->t('Embed code'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name)
  {
    // Get the text_long field where the embed markup is stored
    $remote_field = $media->get($this->configuration['source_field']);
    $json_arr = json_decode($remote_field->value);
    return $json_arr->$attribute_name ?? parent::getMetadata($media, $attribute_name);
  }
}
