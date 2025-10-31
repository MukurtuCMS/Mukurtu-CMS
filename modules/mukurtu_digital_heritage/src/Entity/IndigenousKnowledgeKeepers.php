<?php

namespace Drupal\mukurtu_digital_heritage\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_digital_heritage\Entity\IndigenousKnowledgeKeepersInterface;

class IndigenousKnowledgeKeepers extends Paragraph implements IndigenousKnowledgeKeepersInterface
{
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = [];

    $definitions['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name of the Elder or Knowledge Keeper'))
      ->setDescription(t('The Knowledge Keeper\'s name(s), according to their personal or community preferences. This may include their appropriate title or status. </br>Maximum 255 characters.'))
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
      ->setDescription('Including the Knowledge Keeper\'s Nation or community foregrounds the importance of relationships when it comes to Indigenous knowledges and recognizes which nations hold which teachings. The  nation/community  element  of  the  citation  not  only  recognizes  the  important  relationship of an Elder or Knowledge Keeper to their nation or community, it helps celebrate the nuances between different community teachings. </br>Maximum 255 characters.')
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
      ->setDescription('For some individuals, or in relation to some teachings, it may be important for the Knowledge Keeper to acknowledge their relationship to a treaty. This may be particularly important regarding oral teachings about land or treaty rights. Only used if applicable and indicated by the Knowledge Keeper. </br>Maximum 255 characters.')
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
      ->setDescription(t('Knowledge Keepers may live in places that differ from the origins of their knowledge, nation, or birthplace, and, in some cases, this may be an important relationship they want to recognize. Only used if applicable and indicated by the Knowledge Keeper. </br>Maximum 255 characters.'))
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
      ->setDescription('Information such as the familial provenance of the teaching could be included here. For example, the citation might read: “Story about the sisters of the river as told to [Name of story keeper] by their grandmother [or the grandmother’s name].” This element should be discussed with the Knowledge Keeper, to properly describe or classify the knowledge they are sharing. </br>Maximum 255 characters.')
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
      ->setDescription(t('As exact a date as possible is preferred to help other scholars working with the same Knowledge Keeper to identify which teaching was being cited. This can also be key if there are temporal or seasonal restrictions to knowledge, which should be discussed in depth with the Knowledge Keeper if they are considering including that knowledge in a publication that may be read at any time. </br>Enter the year and, if known, select the month or month and day.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }
}
