<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordEntryInterface;

class DictionaryWordEntry extends Paragraph implements DictionaryWordEntryInterface {
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['field_word_entry_term'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Term'))
      ->setDescription(t('A word, term, phrase, or other language element that is is derived from the term field of the main entry. </br>Maximum 255 characters.'))
      ->setDescription(t('A word, term, phrase, or other language element that is is derived from the term field of the main entry. </br>Maximum 255 characters.'))
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

    $definitions['field_alternate_spelling'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Alternate Spelling'))
      ->setDescription(t('An alternate spelling of the term. Examples include historic or current variant spellings, spellings from different dialects or in different writing systems, or any other alternate spelling that will help find the dictionary word when searching. </br>Maximum 255 characters.'))
      ->setDescription(t('An alternate spelling of the term. Examples include historic or current variant spellings, spellings from different dialects or in different writing systems, or any other alternate spelling that will help find the dictionary word when searching. </br>Maximum 255 characters.'))
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
      ->setDescription('A contributor is a person or group who aided in the making of the entry. While a contributor is usually a single person, it could also be a clan, tribe, culture group, or organization. A dictionary word can have multiple contributors. Examples include language speakers who recorded the word, or contributed knowledge and history of the word. </br>Names can be in any format that is appropriate for the content, eg: "John Smith" or "Smith, John". </br>As you type, names of existing contributors will be displayed. Select an existing contributor or enter a new name. To include additional contributors, select "Add another item".')
      ->setDescription('A contributor is a person or group who aided in the making of the entry. While a contributor is usually a single person, it could also be a clan, tribe, culture group, or organization. A dictionary word can have multiple contributors. Examples include language speakers who recorded the word, or contributed knowledge and history of the word. </br>Names can be in any format that is appropriate for the content, eg: "John Smith" or "Smith, John". </br>As you type, names of existing contributors will be displayed. Select an existing contributor or enter a new name. To include additional contributors, select "Add another item".')
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

    $definitions['field_definition'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Definition'))
      ->setDescription('A longer definition or description of the entry. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.')
      ->setDescription('A longer definition or description of the entry. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.')
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_pronunciation'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Pronunciation'))
      ->setDescription(t('A pronunciation guide used to teach language learners the correct pronunciation of the entry. Pronunciation guides may use a standard phonetic alphabet or whatever notation system is used by speakers and teachers of the language, eg: indicating stress with bold text or capitalizing syllables. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setDescription(t('A pronunciation guide used to teach language learners the correct pronunciation of the entry. Pronunciation guides may use a standard phonetic alphabet or whatever notation system is used by speakers and teachers of the language, eg: indicating stress with bold text or capitalizing syllables. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
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
      ->setDescription('Audio recordings of the entry being spoken, usually on its own. Multiple recordings may be used to represent different types of speakers (eg: speakers of different ages, genders, accents, or dialects), or different forms the entry can take. </br>Recordings can be assigned a different cultural protocol from the dictionary word to allow differential access to the recordings and metadata. </br>Select "Add media" to select or upload audio files. Supported file formats: MP3, M4A, WAV, AAC, OGG. </br>Note that the audio file itself includes a contributor field that can be used to record and display the name of the speaker.')
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
      ->setDescription(t('The entry can be included in longer sentences or phrases to provide more context or better show multiple forms of the entry. A single sample sentence can be text only, audio only, or both corresponding text and audio. </br>To include additional sample sentences, select "Add another item".'))
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
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t('Reference to a resource from which the entry was collected or sourced. Examples include a specific dictionary or language researcher, or the places where the entry is used (in the case of dialectical variation, for example). </br>Maximum 255 characters.'))
      ->setDescription(t('Reference to a resource from which the entry was collected or sourced. Examples include a specific dictionary or language researcher, or the places where the entry is used (in the case of dialectical variation, for example). </br>Maximum 255 characters.'))
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
      ->setDescription(t('Translations of the entry into other languages. Consider indicating the language of the translation, eg: Apple (English). </br>Maximum 255 characters. </br>To include additional translations, select "Add another item."'))
      ->setDescription(t('Translations of the entry into other languages. Consider indicating the language of the translation, eg: Apple (English). </br>Maximum 255 characters. </br>To include additional translations, select "Add another item."'))
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
      ->setDescription('Information about the history or etymology of the entry. Examples include the origin language of a borrowed word or the date the word came into the language. </br>Maximum 255 characters.')
      ->setDescription('Information about the history or etymology of the entry. Examples include the origin language of a borrowed word or the date the word came into the language. </br>Maximum 255 characters.')
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
      ->setDescription('Word types may include parts of speech, syntactic or grammatical categories, or any other relevant system to classify entries.	</br>As you type, existing word types will be displayed. Select an existing word type or enter a new one. To include additional word types, select "Add another item".')
      ->setDescription('Word types may include parts of speech, syntactic or grammatical categories, or any other relevant system to classify entries.	</br>As you type, existing word types will be displayed. Select an existing word type or enter a new one. To include additional word types, select "Add another item".')
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
