<?php

namespace Drupal\mukurtu_core\Plugin\media\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Attribute\MediaSource;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;

/**
 * External embed entity media source.
 *
 * @see \Drupal\file\FileInterface
 */
#[MediaSource(
  id: 'external_embed',
  label: new TranslatableMarkup('External Embed'),
  description: new TranslatableMarkup('External embed code.'),
  allowed_field_types: ['text_long'],
)]
class ExternalEmbed extends MediaSourceBase
{
  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(){

  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    switch ($attribute_name) {
      case 'default_name':
        return '';
      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }
}
