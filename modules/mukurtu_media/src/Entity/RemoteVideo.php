<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\media\Entity\Media;
use Drupal\mukurtu_media\Entity\RemoteVideoInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Defines the Remote Video media entity bundle class.
 */
class RemoteVideo extends Media implements RemoteVideoInterface, CulturalProtocolControlledInterface
{
  use CulturalProtocolControlledTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = self::getProtocolFieldDefinitions();

    $definitions['field_media_oembed_video'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Video URL'))
      ->setDescription(t(''))
      ->setDefaultValue('')
      ->setSettings([
        'max_length' => 255
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }
}
