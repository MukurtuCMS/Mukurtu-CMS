<?php

namespace Drupal\mukurtu_digital_heritage\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_digital_heritage\DigitalHeritageInterface;

class DigitalHeritage extends Node implements DigitalHeritageInterface {

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['field_cultural_narrative'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Cultural Narrative')
      ->setDescription(t('The Cultural Narrative field is used to add historical or social context, expert community knowledge, community stories, and other relevant context to the Digital Heritage Item. This is generally information that is community specific. For example, if the item is a basket, this field may contain a narrative from the basket­maker about their technique, or it may tell a story about how the baskets were used by previous generations and how they are used today.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription(t('Field for briefly describing the Media Asset within a Digital Heritage Item. This can include physical characteristics (i.e. photograph, manuscript, newspaper clipping), content information (i.e. what is depicted, content of text), and any other general relevant information. Audio or video are embedded by dragging Media Assets from the media library into this field. For the Media Asset to display correctly there must be a line break or text below where the Media Asset will be embedded. Note, certain media types (eg. audio, Youtube video) do not render fully within the edit box, but will display correctly when the Digital heritage Item is saved. Using the plain text editor setting provides better control over embedded media.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('A text description of the location.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_traditional_knowledge'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Traditional Knowledge')
      ->setDescription(t('The Traditional Knowledge field is used to add in-­depth community-specific knowledge about the Digital Heritage Item, and is often used to provide information of social, spiritual, or esoteric significance. For example, if the item is a basket, this field may contain community, tribe, or clan specific knowledge about the significance of design that is not more generally known.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_date_description'] = BaseFieldDefinition::create('string')
      ->setLabel('Date Description')
      ->setDescription(t('Original Date Description refers to the date of creation of the Media Asset; i.e. when it was written, filmed, recorded, or made. This is an open text field, limited to 255 characters, and is intended for use when a strictly formatted date is not appropriate (eg: Summer 1950, date unknown).'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_source'] = BaseFieldDefinition::create('string')
      ->setLabel('Source')
      ->setDescription(t('Source provides a reference to a resource, collection, or institution from where the Digital Heritage Item or Media Asset is contributed or originated. Examples include collections (e.g., “McWhorter Collection”), institutions (e.g.,: “Library of Congress, American Folklife Center), or donors (e.g.,: “Donated by John Smith”).'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_summary'] = BaseFieldDefinition::create('string')
      ->setLabel('Summary')
      ->setDescription(t('A brief description of the Digital Heritage Item, limited to 255 characters. The summary is displayed with the Digital Heritage Item teaser when browsing, and can help distinguish between items with similar or identical titles. Other fields allow for longer, more in depth description.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel('Identifier')
      ->setDescription(t('A unique, unambiguous reference to the Digital Heritage Item or Media Asset. Identifiers are often provided by the contributing institution or organization so the original item can be located. Examples include call numbers or accession numbers.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_rights'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Rights')
      ->setDescription(t('A statement about who holds the legal rights to the Digital Heritage Item, Media Asset, or presented knowledge. Consider adding contact information if the rights holder should be contacted for permission to use, reproduce, circulate, reference, or cite the Digital Heritage Item.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_transcription'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Transcription')
      ->setDescription(t('Transcription is a plain text field used to provide a text transcription of an audio or video recording, or of text in an image or document. Including a transcription allows the text to be discoverable when searching for Digital Heritage Items.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_protocol_control'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Cultural Protocols'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'protocol_control',
        'handler' => 'default:protocol_control',
        'handler_settings' => [
          'target_bundles' => NULL,
          'auto_create' => FALSE,
        ]
      ])
    ->setCardinality(1)
    ->setRequired(TRUE)
    ->setTranslatable(TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    $definitions['field_mukurtu_original_record'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Original Record'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'digital_heritage' => 'digital_heritage',
          ],
          'auto_create' => FALSE,
        ]
      ])
    ->setCardinality(1)
    ->setRequired(FALSE)
    ->setTranslatable(TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Content'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => NULL,
          'auto_create' => FALSE,
        ]
      ])
    ->setCardinality(-1)
    ->setRequired(FALSE)
    ->setTranslatable(TRUE)
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_assets'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Assets'))
      ->setDescription(t('Media assets are the core element of Digital Heritage Items and can be images, documents, video, or audio files. Digital Heritage Item can include more than one media asset. Media assets are not required for Digital Heritage Items.'))
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
          ],
          'auto_create' => FALSE,
        ]
      ])
    ->setCardinality(-1)
    ->setRequired(FALSE)
    ->setTranslatable(FALSE)
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    $definitions['field_external_links'] = BaseFieldDefinition::create('link')
      ->setLabel(t('External Links'))
      ->setCardinality(-1)
      ->setSettings([
        'title' => 1,
        'link_type' => 17,
      ])
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_creative_commons'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Licensing Options'))
      ->setDescription(t('Creative Commons licenses are an extension of Copyright that allow a copyright holder to specify the ways in which their work may be altered, shared, and used. For more information on Creative Commons licensing, visit <a href="http://creativecommons.org">creativecommons.org</a>'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Location'))
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_original_date'] = BaseFieldDefinition::create('original_date')
      ->setLabel(t('Original Date'))
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_category'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Category'))
      ->setDescription(t('Categories are high-level descriptive terms that group Digital Heritage Items together and help users browse and discover Digital Heritage Items. Categories are unique to each site and reflect the scope of the items included. One set of Categories is used to describe all Digital Heritage Items within the site. Each Digital Heritage Item must belong to at least one Category. Check the box beside each relevant Category.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'category' => 'category'
          ],
          'auto_create' => FALSE,
        ]
      ])
    ->setCardinality(-1)
    ->setRequired(TRUE)
    ->setTranslatable(FALSE)
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    $definitions['field_contributor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Contributor'))
      ->setDescription(t('A Contributor can be a person, people, clan, tribal nation, community group or, organization who aided in making the content of a Digital Heritage Item or Media Asset. This could be the person who wrote, compiled, or illustrated a book or recorded a song; the people who edited or produced a film, or collaborated or consulted on a project. Contributors with semicolons (John Smith; Jane Doe). Commas are valid (last name, first name), as are quotes (John “Nickname” Smith).'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'contributor' => 'contributor'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setDescription(t('A Creator can be a person or people; a clan, tribe, or cultural group; or an organization that is primarily responsible for providing the essential knowledge or labor that goes into making a Digital Heritage Item or Media Asset. For example, the Creator field could list who designed or made a basket; the knowledge holders who provided the information for a book or the book’s author or illustrator; the singers, songwriters, dancers, or performers who bring to life cultural materials. Separate multiple Creators with semicolons (John Smith; Jane Doe). Commas are valid (last name, first name), as are quotes (John "Nickname" Smith).'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'creator' => 'creator'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_format'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Format'))
      ->setDescription(t('Format is the specific physical or digital manifestation of the Media Asset or Digital Heritage Item. Include physical format (eg: pamphlet, glass slide, open reel), duration or extent (eg: 90 minutes, 20 pages), dimensions (eg: 4x6”, 12x6x8cm), digital filetype (eg: PDF, JPG, MP3, MP4), or other details as needed.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'format' => 'format'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keyword'))
      ->setDescription(t('Keywords are terms used to describe a Digital Heritage Item to ensure that the item will be discoverable when searching or browsing. Keywords are more flexible and specific than Categories. Contributors can create new Keywords as needed when creating or editing a Digital Heritage Item. Consider adding 3-­5 Keywords that will help users discover the Digital Heritage Item through searching or browsing. Separate multiple Keywords with semicolons (eg: basket; weaving).'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_language'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Language'))
      ->setDescription(t('The language or languages used in the Digital Heritage Item or Media Asset. This includes text, audio, video.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'language' => 'language'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_people'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('People'))
      ->setDescription(t('The person or people represented or referenced in the Digital Heritage Item or Media Asset. This may be people identifiable in a photograph, people speaking in an audio recording, present in a video, or referenced in a document. The People field is a way to identify people that may have been left out of the record because they were not a Creator or Contributor.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'people' => 'people'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_publisher'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Publisher'))
      ->setDescription(t('A Publisher can be a person, an organization, or a service responsible for publishing the media asset or Digital Heritage Item.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'publisher' => 'publisher'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_subject'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subject'))
      ->setDescription(t('The main topic or topics presented in the Digital Heritage Item. Subjects may be derived from existing classification systems (for example, Library of Congress Classification Numbers or Dewey Decimal numbers), controlled vocabularies (such as Medical Subject Headings or Art and Architecture Thesaurus descriptors), or can be created as needed within the site.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'subject' => 'subject'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('Type is the nature, genre, or function of the Media Asset or Digital Heritage Item. Examples include Image, Text, Sound, Video.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'type' => 'type'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
