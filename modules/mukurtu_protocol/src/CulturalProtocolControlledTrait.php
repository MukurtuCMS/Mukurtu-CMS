<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Field\BaseFieldDefinition;

trait CulturalProtocolControlledTrait {
  /**
   * Return the base field definitions for the protocol fields.
   *
   * @return array
   *   The field definitions.
   */
  public static function getProtocolFieldDefinitions(): array {
    $definitions = [];

    $definitions['field_cultural_protocols'] = BaseFieldDefinition::create('cultural_protocol')
      ->setLabel('Cultural Protocols')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
