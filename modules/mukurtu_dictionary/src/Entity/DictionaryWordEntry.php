<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordEntryInterface;

class DictionaryWordEntry extends Paragraph implements DictionaryWordEntryInterface {

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['field_word_entry_term'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Word Entry Term'))
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

    $definitions['field_contributor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Contributor'))
      ->setDescription('')
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'contributor' => 'contributor'
          ],
          'auto_create' => TRUE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_definition'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Definition'))
      ->setDescription('')
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_pronunciation'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Pronunciation'))
      ->setDescription(t('This field is for making pronunciation guides for word entries. By default this field supports some HTML tags such as "<strong>" and "<em>" if bold or italics are desired.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_recording'] = BaseFieldDefinition::create('entity_reference')
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
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_sample_sentences'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Sample Sentences'))
      ->setDescription(t('One or more example sentences that demonstrate use of the word entry.'))
      ->setSettings([
        'target_type' => 'paragraph',
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => FALSE,
          'target_bundles' => [
            'sample_sentence' => 'sample_sentence'
          ],
          'target_bundles_drag_drop' => [
            'sample_sentence' => [
              'enabled' => TRUE,
              'weight' => 2,
            ],
          ],
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t('Reference to a resource from which the dictionary entry is derived, for example if the entry comes from specific reference material, a regional dialect, etc.'))
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

    $definitions['field_translation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Translation'))
      ->setDescription(t('The word entry term translated (if appropriate) to the primary language of the site.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_word_origin'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Word Origin'))
      ->setDescription('')
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

    $definitions['field_word_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Word Type'))
      ->setDescription('')
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'word_type' => 'word_type',
          ],
          'auto_create' => TRUE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
