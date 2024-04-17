<?php

namespace Drupal\mukurtu_digital_heritage\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_digital_heritage\Entity\IndigenousKnowledgeKeepersInterface;

class IndigenousKnowledgeKeepers extends Paragraph implements IndigenousKnowledgeKeepersInterface
{
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = [];

    $definitions['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name of the Elder or Knowledge Keeper'))
      ->setDescription(t(''))
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

    $definitions['field_nation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nation or Community'))
      ->setDescription('')
      ->setSettings([
        'max_length' => 255,
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_treaty_territory'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Treaty Territory'))
      ->setDescription('')
      ->setSettings([
        'max_length' => 255,
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_living_place'] = BaseFieldDefinition::create('string')
      ->setLabel(t('City or Community They Live in'))
      ->setDescription(t(''))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_teaching'] = BaseFieldDefinition::create('string')
      ->setLabel(t('A Brief Description or Title of the Teaching'))
      ->setDescription('')
      ->setSettings([
        'max_length' => 255,
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_original_date'] = BaseFieldDefinition::create('original_date')
      ->setLabel(t('Date'))
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }
}
