<?php

namespace Drupal\mukurtu_media\Entity;

use Drupal\media\Entity\Media;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\mukurtu_media\Entity\SoundCloudInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Defines the SoundCloud media entity bundle class.
 */
class SoundCloud extends Media implements SoundCloudInterface, CulturalProtocolControlledInterface
{
  use CulturalProtocolControlledTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
  {
    $definitions = self::getProtocolFieldDefinitions();

    $definitions['field_media_soundcloud'] = BaseFieldDefinition::create('string')
      ->setLabel(t('SoundCloud URL'))
      ->setDescription(t('A track, playlist, or album hosted on SoundCloud. The remote file will stream using SoundCloud\'s media player, and is not copied to the site. Note that while the media asset will be managed by cultural protocols, the originating website may not have similar privacy settings.	</br>Enter the URL of the SoundCloud track, playlist, or album.'))
      ->setDefaultValue('')
      ->setSettings([
        'max_length' => 255,
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_media_tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Media Tags'))
      ->setDescription(t('Media tags are used to label media assets to help find them within the media library. They are also used to trigger taxonomy based media content warnings.	</br>As you type, existing media tags will be displayed. Select an existing media tag or enter a new term. To include additional media tags, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'media_tag' => 'media_tag'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => ''
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
      ->setDescription(t('A person or people present or referenced in the document. This is used to trigger deceased person media content warnings.	</br>As you type, names of existing people will be displayed. Select an existing person or enter a new name. To include additional people, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'people' => 'people'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => ''
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_thumbnail'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Thumbnail'))
      ->setDescription(t('SoundCloud audio is usually represented by an interactive audio player, with a thumbnail image used in certain contexts. When the thumbnail image is used, you can provide your own image instead of the generic thumbnail. A thumbnail image does not usually need to be provided.	</br>Select "Choose File" to upload a thumbnail image.'))
      ->setDefaultValue('')
      ->setSettings([
        'alt_field' => TRUE,
        'alt_field_required' => TRUE,
        'title_field' => FALSE,
        'title_field_required' => FALSE,
        'max_resolution' => '',
        'min_resolution' => '',
        'default_image' => [
          'uuid' => NULL,
          'alt' => '',
          'title' => '',
          'width' => NULL,
          'height' => NULL,
        ],
        'file_directory' => '[date:custom:Y]-[date:custom:m]',
        'file_extensions' => 'png gif jpg jpeg',
        'max_filesize' => '',
        'handler' => 'default:file',
        'uri_scheme' => 'private',
        'display_field' => FALSE,
        'display_default' => FALSE,
        'target_type' => 'file'
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel('Identifier')
      ->setDescription('A unique, unambiguous reference to the media asset. Identifiers are often provided by the contributing institution or organization so the original item can be located. Examples include call numbers or accession numbers. Maximum 255 characters.')
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
      ->setDescription(t('Speakers or singers present in the audio file. Contributors listed here are displayed in the speaker field that accompanies the audio file in dictionary words.</br>	As you type, names of existing contributors will be displayed. Select an existing contributor or enter a new name. To include additional contributors, select "Add another item".'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'contributor' => 'contributor'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => ''
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
