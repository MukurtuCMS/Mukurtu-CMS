<?php

namespace Drupal\mukurtu_person\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

class RelatedPerson extends Paragraph {
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];
    $definitions['field_related_person'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Person'))
      ->setDescription(t('A person that has a relationship with the subject of the person record. </br>Select "Select Content" to choose from existing person records.'))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'person' => 'person',
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_relationship_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Relationship Type'))
      ->setDescription('The type of relationship between the related person and the subject of the person record. </br>As you type, existing relationship types will be displayed. Select an existing relationship type or enter a new one. To include additional relationship types, select "Add another item".')
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'interpersonal_relationship' => 'interpersonal_relationship'
          ],
          'auto_create' => TRUE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

}
