<?php

namespace Drupal\mukurtu_place\Entity;

use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_place\PlaceInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;

class Place extends Node implements PlaceInterface, CulturalProtocolControlledInterface, MukurtuDraftInterface {
  use CulturalProtocolControlledTrait;
  use MukurtuDraftTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    // Add the drafts field.
    $definitions += static::draftBaseFieldDefinitions($entity_type);

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t('Keywords are used to tag place records to ensure that they are discoverable when searching or browsing.	As you type, existing keywords will be displayed. </br>Select an existing keyword or enter a new one. To include additional keywords, select "Add another item".'))
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
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_assets'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Assets'))
      ->setDescription(t('Media assets are a key element of most place records, though they are not required. Supported media types are images, documents, video, audio, and embed code. Place records can include more than one media asset, and each media asset can be a different media type. Media assets can be assigned a different cultural protocol from the place record to allow differential access to the media assets and metadata.	</br>Select "Add media" to select or upload media assets.'))
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
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_sections'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(t('Text sections'))
      ->setDescription(t('TBD.	Biography sections can be rearranged and collapsed for easier editing. To add additional biography sections, select "Add Biography section".'))
      ->setSettings([
        'target_type' => 'paragraph',
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => FALSE,
          'target_bundles' => [
            'text_section_with_title' => 'text_section_with_title'
          ],
          'target_bundles_drag_drop' => [
            'text_section_with_title' => [
              'enabled' => TRUE,
              'weight' => 2,
            ],
          ],
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Content'))
      ->setDescription(t('Place records can be related to any other site content when there is a connection between those items that is important to show. Note that the place record will automatically aggregate all content where the place is referenced based on the representative terms field, and manually managing related content may not be necessary. </br>Select "Select Content" to choose from existing site content.'))
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
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_other_names'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Other Names'))
      ->setDescription(t('Places may be identified by multiple names, monikers, identities, and with inconsistent spellings across different content. This field is used to aggregate and display all content where the place is identified by connecting those disparate names.	</br>Select "Select Terms" to choose from existing names. Choose all names representing this place. </br>Each taxonomy (eg: location, keywords) must first be enabled by a Mukurtu Manager. New names cannot be added here and must already be in used in existing site content, in an enabled taxonomy.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'location' => 'location',
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => 'location',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Map Points'))
      ->setDescription(t('A detailed, interactive mapping tool that allows placing and drawing multiple locations related to a place record. Locations can be single points, paths, rectangles, or free-form polygons. Each location can be given a basic label. This field is also used for the browse by map tools. Note that this mapping data will be shared with the same users or visitors as the rest of the place record. If the location is sensitive, carefully consider using this field."	</br>Use the tools shown on the map to place, draw, edit, and delete points and shapes. Once a point or shape has been placed, select it to add a description if needed.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('A descriptive field to provide additional context and depth to the location(s) connected to the place record.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_location'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Location'))
      ->setDescription(t('A named place, or places, that are closely connected to the place record.	</br>As you type, existing locations will be displayed. Select an existing location or enter a new one. To include additional locations, select "Add another item".'))
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

    $definitions['field_local_contexts_projects'] = BaseFieldDefinition::create('local_contexts_project')
      ->setLabel(t('Local Contexts Projects'))
      ->setDescription(t('This field will apply all of the Labels from the selected Local Contexts Project(s) to the place record.	</br>Select one or more Local Contexts Projects.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_labels_and_notices'] = BaseFieldDefinition::create('local_contexts_label_and_notice')
      ->setLabel(t('Local Contexts Labels and Notices'))
      ->setDescription(t('This field allows selective application of one or more Labels from any available Local Contexts Project to the place record.	Local Contexts Project ID:Label/Notice ID values, separated by your selected multi-value delimiter.	Select one or more Labels from the appropriate Local Contexts Project. If a complete project has already been selected, do not also select individual Labels from the same project.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
