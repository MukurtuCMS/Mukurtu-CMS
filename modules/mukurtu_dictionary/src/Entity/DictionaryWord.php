<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\Core\Session\AccountInterface;
use \Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_core\Entity\BundleSpecificCheckCreateAccessInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;

class DictionaryWord extends Node implements DictionaryWordInterface, CulturalProtocolControlledInterface, BundleSpecificCheckCreateAccessInterface, MukurtuDraftInterface {
  use CulturalProtocolControlledTrait;
  use MukurtuDraftTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = self::getProtocolFieldDefinitions();

    // Add the drafts field.
    $definitions += static::draftBaseFieldDefinitions($entity_type);

    /**
     * NOTE 11-6-24:
     *
     * Why does Dictionary Word include nearly all of word entry's fields?
     * We were running into an issue where we wanted the word entry paragraph
     * title field to have a different name depending on whether it was a base word entry
     * or an additional word entry. Base word entry title would be 'Term' while
     * additional word entry title would be 'Word Entry Term'.
     *
     * I didn't see a way to conditionally change the title field like that, so
     * instead I just copied all word entry fields except the term field to dictionary
     * word.
     */

    // Dictionary Word Base Entry
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

    $definitions['field_glossary_entry'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Glossary Entry'))
      ->setDescription(t('By default, the dictionary word will be indexed, sorted, or alphabetized by the first character of the term. The glossary entry is used for indexing when a character or letter other than the first character in the term should be referenced. Examples include diacritic or accent-initial words, root word markers at the start of the term, or combined characters (eg: ch or á in some languages are considered a single character).'))
      ->setDescription(t('By default, the dictionary word will be indexed, sorted, or alphabetized by the first character of the term. The glossary entry is used for indexing when a character or letter other than the first character in the term should be referenced. Examples include diacritic or accent-initial words, root word markers at the start of the term, or combined characters (eg: ch or á in some languages are considered a single character).'))
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

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t('Keywords are used to tag dictionary words to ensure that they are discoverable when searching or browsing.	</br>As you type, existing keywords will be displayed. Select an existing keyword or enter a new one. To include additional keywords, select "Add another item".'))
      ->setDescription(t('Keywords are used to tag dictionary words to ensure that they are discoverable when searching or browsing.	</br>As you type, existing keywords will be displayed. Select an existing keyword or enter a new one. To include additional keywords, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc'
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Map Points'))
      ->setDescription(t('A detailed, interactive mapping tool that allows placing and drawing multiple locations related to a dictionary word. Locations can be single points, paths, rectangles, or free-form polygons. Each location can be given a basic label. This field is also used for the browse by map tools. Note that this mapping data will be shared with the same users or visitors as the rest of the dictionary word. If the location is sensitive, carefully consider using this field.	</br>Use the tools shown on the map to place, draw, edit, and delete points and shapes. Once a point or shape has been placed, select it to add a description if needed.'))
      ->setDescription(t('A detailed, interactive mapping tool that allows placing and drawing multiple locations related to a dictionary word. Locations can be single points, paths, rectangles, or free-form polygons. Each location can be given a basic label. This field is also used for the browse by map tools. Note that this mapping data will be shared with the same users or visitors as the rest of the dictionary word. If the location is sensitive, carefully consider using this field.	</br>Use the tools shown on the map to place, draw, edit, and delete points and shapes. Once a point or shape has been placed, select it to add a description if needed.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('A descriptive field to provide additional context and depth to the location(s) connected to the dictionary word.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setDescription(t('A descriptive field to provide additional context and depth to the location(s) connected to the dictionary word.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_location'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Location'))
      ->setDescription(t('A named place, or places, that are closely connected to the dictionary word. Examples include words that are place names,  where a word originated, or a place the word is otherwise connected to. </br>As you type, existing locations will be displayed. Select an existing location or enter a new one. To include additional locations, select "Add another item".'))
      ->setDescription(t('A named place, or places, that are closely connected to the dictionary word. Examples include words that are place names,  where a word originated, or a place the word is otherwise connected to. </br>As you type, existing locations will be displayed. Select an existing location or enter a new one. To include additional locations, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'location' => 'location'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc'
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_dictionary_word_language'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Language'))
      ->setDescription(t('Each dictionary word is associated with one language. </br>As you type, existing languages will be displayed. Select a language. Languages must first be created by a Mukurtu Manager, or referenced in a digital heritage item.'))
      ->setDescription(t('Each dictionary word is associated with one language. </br>As you type, existing languages will be displayed. Select a language. Languages must first be created by a Mukurtu Manager, or referenced in a digital heritage item.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'language' => 'language'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_assets'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Assets'))
      ->setDescription(t('Additional media assets can further enrich a dictionary word. Examples include photos of a plant or animal named in the word, a longer video of the word being taught, or a relevant page from a language learning book. Supported media types are images, documents, video, audio, and embed code. dictionary words can include more than one media asset, and each media asset can be a different media type. Media assets can be assigned a different cultural protocol from the dictionary word to allow differential access to the media assets and metadata. </br>Select "Add media" to select or upload media assets.'))
      ->setDescription(t('Additional media assets can further enrich a dictionary word. Examples include photos of a plant or animal named in the word, a longer video of the word being taught, or a relevant page from a language learning book. Supported media types are images, documents, video, audio, and embed code. dictionary words can include more than one media asset, and each media asset can be a different media type. Media assets can be assigned a different cultural protocol from the dictionary word to allow differential access to the media assets and metadata. </br>Select "Add media" to select or upload media assets.'))
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'audio' => 'audio',
            'document' => 'document',
            'image' => 'image',
            'remote_video' => 'remote_video',
            'video' => 'video',
            'soundcloud' => 'soundcloud'
          ],
          'sort' => [
            'field' => '_none'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => 'audio',
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
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

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Content'))
      ->setDescription(t('Dictionary words can be related to any other site content when there is a connection between those items that is important to show. Examples include digital heritage items that include the word. 	</br>Select "Select Content" to choose from existing site content.'))
      ->setDescription(t('Dictionary words can be related to any other site content when there is a connection between those items that is important to show. Examples include digital heritage items that include the word. 	</br>Select "Select Content" to choose from existing site content.'))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => NULL,
          'sort' => [
            'field' => '_none'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => 'article',
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_thumbnail'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Thumbnail'))
      ->setDescription(t('The thumbnail image is a clear visual example or illustration of the dictionary word. It is included in previews along with the term, translation, and recording fields.	</br>Select "Add media" to select or upload an image.'))
      ->setDescription(t('The thumbnail image is a clear visual example or illustration of the dictionary word. It is included in previews along with the term, translation, and recording fields.	</br>Select "Add media" to select or upload an image.'))
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image'
          ],
          'sort' => [
            'field' => '_none',
            'direction' => 'ASC'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_projects'] = BaseFieldDefinition::create('local_contexts_project')
      ->setLabel(t('Local Contexts Projects'))
      ->setDescription(t('This field will apply all of the Labels from the selected Local Contexts Project(s) to the dictionary word.	</br>Select one or more Local Contexts Projects.'))
      ->setDescription(t('This field will apply all of the Labels from the selected Local Contexts Project(s) to the dictionary word.	</br>Select one or more Local Contexts Projects.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_labels_and_notices'] = BaseFieldDefinition::create('local_contexts_label_and_notice')
      ->setLabel(t('Local Contexts Labels and Notices'))
      ->setDescription(t('This field allows selective application of one or more Labels from any available Local Contexts Project to the dictionary word.	</br>Select one or more Labels from the appropriate Local Contexts Project. If a complete project has already been selected, do not also select individual Labels from the same project.'))
      ->setDescription(t('This field allows selective application of one or more Labels from any available Local Contexts Project to the dictionary word.	</br>Select one or more Labels from the appropriate Local Contexts Project. If a complete project has already been selected, do not also select individual Labels from the same project.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);


    // Dictionary Word Additional Entries (paragraph)
    $definitions['field_additional_word_entries'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Word Entries'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'paragraph',
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => FALSE,
          'target_bundles' => [
            'dictionary_word_entry' => 'dictionary_word_entry'
          ],
          'target_bundles_drag_drop' => [
            'dictionary_word_entry' => [
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
      return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage)
  {
    if ($this->hasField('field_glossary_entry')) {
      if (empty($this->get('field_glossary_entry')->getValue())) {
        $this->set("field_glossary_entry", mb_substr($this->getTitle(), 0, 1));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleCheckCreateAccess(AccountInterface $account, array $context): AccessResult {
    // Dictionary words require at least one language to be present on the site.
    $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery();
    $languages = $query->condition('vid', 'language')->accessCheck(TRUE)->execute();
    return AccessResult::allowedIf(!empty($languages));
  }

}
