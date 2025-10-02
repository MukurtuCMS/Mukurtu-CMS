<?php

namespace Drupal\mukurtu_digital_heritage\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_digital_heritage\DigitalHeritageInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;

class DigitalHeritage extends Node implements DigitalHeritageInterface, CulturalProtocolControlledInterface, MukurtuDraftInterface {
  use CulturalProtocolControlledTrait;
  use MukurtuDraftTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    // Add the drafts field.
    $definitions += static::draftBaseFieldDefinitions($entity_type);

    $definitions['field_cultural_narrative'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Cultural Narrative')
      ->setDescription(t('Cultural narratives include historical or social context, expert community knowledge, community stories, and other relevant cultural context for the digital heritage item. For example, if the item is a basket this field may contain a narrative from the basket­ maker about their technique, or it may tell a story contrasting how the baskets were used by previous generations and how they are used today. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription(t('Descriptions provide an account, explanation, or description of the digital heritage item or media asset. This may include physical characteristics, content information, an explanation of what is depicted, digitization and processing information, general notes, and any other relevant information that does not fit into a more structured field. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('A descriptive field to provide additional context and depth to the location(s) connected to the digital heritage item. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_traditional_knowledge'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Traditional Knowledge')
      ->setDescription(t('Traditional knowledge includes in-­depth community-specific knowledge about the digital heritage item. It is often used to provide information of social, spiritual, or esoteric significance. For example, if the item is a basket, this field may contain community, tribe, or clan-specific knowledge about the significance of design that is not widely known or that is specific that that community. </br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_date_description'] = BaseFieldDefinition::create('string')
      ->setLabel('Date Description')
      ->setDescription(t('Used to supplement the original date field, or when a precise creation date of the media asset or information represented in the digital heritage item is not known. Examples include "Summer 1995" and "circa 1800s". </br>Maximum 255 characters.'))
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

    $definitions['field_source'] = BaseFieldDefinition::create('string')
      ->setLabel('Source')
      ->setDescription(t('Source provides a reference to the resource, collection, or institution where the digital heritage item is held, described, originated, or contributed. Examples include collections (e.g., “McWhorter Collection”), institutions (e.g.,: “Library of Congress, American Folklife Center), or donors (e.g.,: “Donated by John Smith”). </br>Maximum 255 characters.'))
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

    $definitions['field_summary'] = BaseFieldDefinition::create('string')
      ->setLabel('Summary')
      ->setDescription(t('A short summary of the digital heritage item. The summary should supplement the title and help help distinguish between similar items. The summary is displayed as part of the item preview when browsing the site. </br>Maximum 255 characters.'))
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

    $definitions['field_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel('Identifier')
      ->setDescription(t('A unique, unambiguous reference to the digital heritage item or media asset Identifiers are often provided by the contributing institution or organization so the original item can be located. Examples include call numbers or accession numbers. </br>Maximum 255 characters.'))
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

    $definitions['field_rights_and_usage'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Rights and Usage')
      ->setDescription(t('A statement about the approriate rights and usage regarding the digital heritage item, media asset, or presented knowledge. This may include identifying the legal or traditional rights holder. If the rights holder should be contacted for permission to use, reproduce, circulate, reference, or cite the item, provide their contact information. </br>Maximum 255 characters.'))
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

    $definitions['field_knowledge_keepers'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel('Citing Indigenous Elders and Knowledge Keepers')
      ->setDescription(t('For a complete definition of this citation method and its fields, see "<a href="https://doi.org/10.18357/kula.135">More Than Personal Communication: Templates For Citing Indigenous Elders and Knowledge Keepers</a>"  by Lorisia MacLeod.'))
      ->setSettings([
        'max_length' => 255,
        'target_type' => 'paragraph',
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => FALSE,
          'target_bundles' => [
            'indigenous_knowledge_keepers' => 'indigenous_knowledge_keepers'
          ],
          'target_bundles_drag_drop' => [
            'indigenous_knowledge_keepers' => [
              'enabled' => TRUE,
              'weight' => 6,
            ],
          ],
        ]
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_transcription'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Transcription')
      ->setDescription(t('A basic text transcription of an audio or video recording, or of text in an image or document. Including a transcription allows the text to be discoverable when searching for digital heritage items.'))
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
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Digital heritage items can be related to any other site content when there is a connection between those items that is important to show. Examples include multiple photos of a place, objects discussed in an oral history, or dictionary words that appear in a digital heritage item. </br>Select "Select Content" to choose from existing site content.'))
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
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_assets'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Assets'))
      ->setDescription(t('Media assets are a key element of most digital heritage items, though they are not required. Supported media types are images, documents, video, audio, and embed code. Digital heritage items can include more than one media asset, and each media asset can be a different media type. Media assets can be assigned a different cultural protocol from the digital heritage item to allow differential access to the media assets and metadata. </br>Select "Add media" to select or upload media assets.'))
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
            'external_embed' => 'external_embed',
            'soundcloud' => 'soundcloud'
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_external_links'] = BaseFieldDefinition::create('link')
      ->setLabel(t('External Links'))
      ->setDescription(t('Links to other records or websites where the digital heritage item is available online, or to other related websites. Examples include the online catalog of the holding repository, or the publisher\'s listing. </br>To include additional links, select "Add another item".'))
      ->setCardinality(-1)
      ->setSettings([
        'title' => 1,
        'link_type' => 16,
      ])
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_creative_commons'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Creative Commons Licenses'))
      ->setDescription(t('Creative Commons licenses provide a standardized way for copyright holders to grant the public permission to use their creative work under copyright law, and to specify the ways in which their work may be altered, shared, and used. For more information, visit <a href="https://creativecommons.org/">creativecommons.org</a>. </br>Select a Creative Commons license from the dropdown menu.'))
      ->setSettings([
        'allowed_values' => [
          'http://creativecommons.org/licenses/by/4.0' => t('Attribution 4.0 International (CC BY 4.0)'),
          'http://creativecommons.org/licenses/by-nc/4.0' => t('Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)'),
          'http://creativecommons.org/licenses/by-sa/4.0' => t('Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)'),
          'http://creativecommons.org/licenses/by-nc-sa/4.0' => t('Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)'),
          'http://creativecommons.org/licenses/by-nd/4.0' => t('Attribution-NoDerivatives 4.0 International (CC BY-ND 4.0)'),
          'http://creativecommons.org/licenses/by-nc-nd/4.0' => t('Attribution-NonCommercial-NoDerivatives 4.0 International (CC BY-NC-ND 4.0)'),
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
      ->setDescription(t('This field will apply all of the Labels from the selected Local Contexts Project(s) to the digital heritage item. </br>Select one or more Local Contexts Projects.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_labels_and_notices'] = BaseFieldDefinition::create('local_contexts_label_and_notice')
      ->setLabel(t('Local Contexts Labels and Notices'))
      ->setDescription(t('This field allows selective application of one or more Labels from any available Local Contexts Project to the digital heritage item. </br>Select one or more Labels from the appropriate Local Contexts Project. If a complete project has already been selected, do not also select individual Labels from the same project.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_rights_statements'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Rights Statements'))
      ->setDescription(t('"RightsStatements.org provides standardized rights statements that can be used by cultural heritage institutions to indicate the copyright status of digital objects that they make available online, either on their own website or via aggregation platforms. These rights statements are high level summaries of the underlying rights status of the digital objects that they apply to. These rights statements are intended to be used in addition to (more detailed) rights information that institutions already have and not to replace existing information." For more information, visit <a href="https://rightsstatements.org/">rightsstatements.org</a>. </br>Select a Rights Statement from the dropdown menu. '))
      ->setSettings([
        'allowed_values' => [
          'http://rightsstatements.org/vocab/InC/1.0/' => t('<img src="https://rightsstatements.org/files/icons/InC.Icon-Only.dark.svg" height="15" width="15" alt="In Copyright"/>
          <a href="@in-copyright">In Copyright</a>', [
            '@in-copyright' => 'http://rightsstatements.org/vocab/InC/1.0/'
          ]),
          'http://rightsstatements.org/vocab/InC-OW-EU/1.0/' => t('<img src="https://rightsstatements.org/files/icons/InC.Icon-Only.dark.svg" height="15" width="15" alt="In Copyright - EU Orphan Work"/>
          <a href="@in-copyright-eu-orphan-work">In Copyright - EU Orphan Work</a>', [
            '@in-copyright-eu-orphan-work' => 'http://rightsstatements.org/vocab/InC-OW-EU/1.0/'
          ]),
          'http://rightsstatements.org/vocab/InC-EDU/1.0/' => t('<img src="https://rightsstatements.org/files/icons/InC.Icon-Only.dark.svg" height="15" width="15" alt="In Copyright - Educational Use Permitted"/>
          <a href="@in-copyright-educational-use-permitted">In Copyright - Educational Use Permitted</a>', [
            '@in-copyright-educational-use-permitted' => 'http://rightsstatements.org/vocab/InC-EDU/1.0/'
          ]),
          'http://rightsstatements.org/vocab/InC-NC/1.0/' => t('<img src="https://rightsstatements.org/files/icons/InC.Icon-Only.dark.svg" height="15" width="15" alt="In Copyright - Non-Commercial Use Permitted"/>
          <a href="@in-copyright-non-commercial-use-permitted">In Copyright - Non-Commercial Use Permitted</a>', [
            '@in-copyright-non-commercial-use-permitted' => 'http://rightsstatements.org/vocab/InC-NC/1.0/'
          ]),
          'http://rightsstatements.org/vocab/InC-RUU/1.0/' => t('<img src="https://rightsstatements.org/files/icons/InC.Icon-Only.dark.svg" height="15" width="15" alt="In Copyright - Rights-Holder(s) Unlocatable or Unidentifiable"/>
          <a href="@in-copyright-rights-holder(s)-unlocatable-or-unidentifiable">In Copyright - Rights-Holder(s) Unlocatable or Unidentifiable</a>', [
            '@in-copyright-rights-holder(s)-unlocatable-or-unidentifiable' => 'http://rightsstatements.org/vocab/InC-RUU/1.0/'
          ]),
          'http://rightsstatements.org/vocab/NoC-CR/1.0/' => t('<img src="https://rightsstatements.org/files/icons/NoC.Icon-Only.dark.svg" height="15" width="15" alt="No Copyright - Contractual Restrictions"/>
          <a href="@no-copyright-contractual-restrictions">No Copyright - Contractual Restrictions</a>', [
            '@no-copyright-contractual-restrictions' => 'http://rightsstatements.org/vocab/NoC-CR/1.0/'
          ]),
          'http://rightsstatements.org/vocab/NoC-NC/1.0/' => t('<img src="https://rightsstatements.org/files/icons/NoC.Icon-Only.dark.svg" height="15" width="15" alt="No Copyright - Non-Commercial Use Only"/>
          <a href="@no-copyright-non-commercial-use-only">No Copyright - Non-Commercial Use Only</a>', [
            '@no-copyright-non-commercial-use-only' => 'http://rightsstatements.org/vocab/NoC-NC/1.0/'
          ]),
          'http://rightsstatements.org/vocab/NoC-OKLR/1.0/' => t('<img src="https://rightsstatements.org/files/icons/NoC.Icon-Only.dark.svg" height="15" width="15" alt="No Copyright - Other Known Legal Restrictions"/>
          <a href="@no-copyright-other-known-legal-restrictions">No Copyright - Other Known Legal Restrictions</a>', [
            '@no-copyright-other-known-legal-restrictions' => 'http://rightsstatements.org/vocab/NoC-OKLR/1.0/'
          ]),
          'http://rightsstatements.org/vocab/NoC-US/1.0/' => t('<img src="https://rightsstatements.org/files/icons/NoC.Icon-Only.dark.svg" height="15" width="15" alt="No Copyright - United States"/>
          <a href="@no-copyright-united-states">No Copyright - United States</a>', [
            '@no-copyright-united-states' => 'http://rightsstatements.org/vocab/NoC-US/1.0/'
          ]),
          'http://rightsstatements.org/vocab/CNE/1.0/' => t('<img src="https://rightsstatements.org/files/icons/Other.Icon-Only.dark.svg" height="15" width="15" alt="Copyright Not Evaluated"/>
          <a href="@copyright-not-evaluated">Copyright Not Evaluated</a>', [
            '@copyright-not-evaluated' => 'http://rightsstatements.org/vocab/CNE/1.0/'
          ]),
          'http://rightsstatements.org/vocab/UND/1.0/' => t('<img src="https://rightsstatements.org/files/icons/Other.Icon-Only.dark.svg" height="15" width="15" alt="Copyright Undetermined"/>
          <a href="@copyright-undetermined">Copyright Undetermined</a>', [
            '@copyright-undetermined' => 'http://rightsstatements.org/vocab/UND/1.0/'
          ]),
          'http://rightsstatements.org/vocab/NKC/1.0/' => t('<img src="https://rightsstatements.org/files/icons/Other.Icon-Only.dark.svg" height="15" width="15" alt="No Known Copyright"/>
          <a href="@no-known-copyright">No Known Copyright</a>', [
            '@no-known-copyright' => 'http://rightsstatements.org/vocab/NKC/1.0/'
          ]),
        ],
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Map Points'))
      ->setDescription(t('A detailed, interactive mapping tool that allows placing and drawing multiple locations related to a digital heritage item. Locations can be single points, paths, rectangles, or free-form polygons. Each location can be given a basic label. This field is also used for the browse by map tools. </br>Note that this mapping data will be shared with the same users or visitors as the rest of the digital heritage item. If the location is sensitive, carefully consider using this field. </br>Use the tools shown on the map to place, draw, edit, and delete points and shapes. Once a point or shape has been placed, select it to add a description if needed.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_location'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Location'))
      ->setDescription(t('A named place, or places, that are closely connected to the digital heritage item. Examples include the location where a photo was taken, places named in a story, or the site where an object was created. </br>As you type, existing locations will be displayed. Select an existing location or enter a new one. To include additional locations, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'location' => 'location'
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

    $definitions['field_original_date'] = BaseFieldDefinition::create('original_date')
      ->setLabel(t('Original Date'))
      ->setDescription(t('The creation date of the media asset or information represented in the digital heritage item. The date should be as precise as possible, and either the year, year-month, or year-month-day can be entered. </br>Note that this is not the date the digital heritage item was published on the site - that is recorded with an automated date stamp. </br>Enter the year and, if known, select the month or month and day.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_category'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Category'))
      ->setDescription(t('Categories are high-level descriptive terms that help users browse and discover digital heritage items. Each site defines their own set of categories to reflect the scope of their items. Each digital heritage item requires at least one category, but more can be selected as needed. </br>Select one or more categories. Categories must first be created by a Mukurtu Manager.'))
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
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_contributor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Contributor'))
      ->setDescription(t('A contributor is a person or group who aided in the making of a digital heritage item. While a contributor is usually a single person, it could also be a clan, tribe, culture group, or organization. A digital heritage item can have multiple contributors. Examples include someone who wrote, compiled, or illustrated a book or recorded a song, the people who edited or produced a film, or people collaborated or consulted on a project. </br>Names can be in any format that is appropriate for the content, eg: ""John Smith"" or ""Smith, John"". </br> As you type, names of existing contributors will be displayed. Select an existing contributor or enter a new name. To include additional contributors, select "Add another item".'))
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

    $definitions['field_creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setDescription(t('A creator is the person or group primarily responsible for making  or providing the core media assets or knowledge represented in a digital heritage item. While a creator is usually a single person, it could also be a clan, tribe, culture group, or organization. A digital heritage item can have multiple creators. Examples include a basket designer or weaver, knowledge holders who provided information for a book, a book’s author or illustrator, singers, songwriters, dancers, or performers. </br>Names can be in any format that is appropriate for the content, eg: "John Smith" or "Smith, John". </br>Note that this is not the user publishing the digital heritage item on the site - that information is recorded in the automated author field.As you type, names of existing creators will be displayed. Select an existing creator or enter a new name. To include additional creators, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'creator' => 'creator'
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

    $definitions['field_format'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Format'))
      ->setDescription(t('Format is the specific physical and/or digital properties of the media asset(s) represented in the digital heritage item. This may include details about the physical format (eg: pamphlet, glass slide, open reel tape), duration (eg: 90 minutes), extent (eg: 20 pages, 11 sheets), dimensions (eg: 4x6", 12x6x8cm), file format (eg: PDF, JPG, MP3), or additional information as needed. </br>As you type, existing formats will be displayed. Select an existing format or enter a new one. To include additional formats, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'format' => 'format'
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

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t('Keywords are used to tag digital heritage items to ensure that the items are discoverable when searching or browsing. They are often used to supplement categories as they can be created on the fly and may be more specific. </br>As you type, existing keywords will be displayed. Select an existing keyword or enter a new one. To include additional keywords, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords'
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

    $definitions['field_language'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Language'))
      ->setDescription(t('Language(s) present in the digital heritage item. This includes the textual metadata and any media assets. </br>As you type, existing languages will be displayed. Select an existing language or enter a new one. To include additional languages, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'language' => 'language'
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

    $definitions['field_people'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('People'))
      ->setDescription(t('A person or people represented or referenced in the digital heritage item or media asset. This field complements the creator and contributor fields. Examples include people identifiable in a photograph, people speaking in an audio recording, present in a video, or referenced in a document. </br>Names can be in any format that is appropriate for the content, eg: ""John Smith"" or ""Smith, John"". </br>As you type, names of existing people will be displayed. Select an existing person or enter a new name. To include additional people, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'people' => 'people'
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

    $definitions['field_publisher'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Publisher'))
      ->setDescription(t('The person, organization, or service responsible for publishing the digital heritage item or media asset.	As you type, existing publishers will be displayed. </br>Select an existing publisher or enter a new one. To include additional publishers, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'publisher' => 'publisher'
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

    $definitions['field_subject'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subject'))
      ->setDescription(t('Subject reflects the main topic(s) of the digital heritage item. It is primarily used to reference existing controlled vocabularies (eg: Library of Congress Subject Headings or Getty Art and Architecture Thesaurus), but a site-specific subject list can be developed as well. </br>As you type, existing subjects will be displayed. Select an existing subject or enter a new one. To include additional subjects, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'subject' => 'subject'
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

    $definitions['field_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('Type is the broad nature, genre, or function of the media asset(s) represented in the digital heritage item. Examples include Image, Text, Sound, Video, or Physical Object.	As you type, existing types will be displayed. Select an existing type or enter a new one. </br>To include additional types, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'type' => 'type'
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

    return $definitions;
  }

}
