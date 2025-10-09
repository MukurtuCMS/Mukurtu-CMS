<?php

namespace Drupal\mukurtu_person\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

class FormattedTextWithTitle extends Paragraph {
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];
    $definitions['field_title'] = BaseFieldDefinition::create('string')
      ->setLabel('Title')
      ->setDescription('The title of the biography section. Examples include "Early life", "Education", or "Professional career". Maximum 255 characters.')
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

    $definitions['field_body'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Body')
      ->setDescription('The body of the biography section.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
