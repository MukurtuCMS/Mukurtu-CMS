<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_dictionary\Entity\SampleSentenceInterface;

class SampleSentence extends Paragraph implements SampleSentenceInterface {

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['field_sentence'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Sample Sentence'))
      ->setDescription(t('One or more example sentences that demonstrate use of the word entry.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_sentence_recording'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recording'))
      ->setDescription('')
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'audio' => 'audio',
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
