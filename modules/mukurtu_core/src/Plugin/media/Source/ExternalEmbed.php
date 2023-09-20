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
  public function getMetadataAttributes(){}
}
