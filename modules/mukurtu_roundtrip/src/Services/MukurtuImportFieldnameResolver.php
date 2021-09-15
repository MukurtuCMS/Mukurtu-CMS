<?php

namespace Drupal\mukurtu_roundtrip\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

class MukurtuImportFieldnameResolver {
  protected $entityManager;
  protected $entityFieldManager;
  protected $fieldMappings;

  public function __construct(EntityTypeManagerInterface $entityManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityManager = $entityManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * Return the field name given a field label/entity type id/bundle.
   */
  protected function buildFieldMappings($entityTypeId, $bundle) {
    $mappings = ['forward' => [], 'reverse' => []];
    $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
    $entityDefinition = $this->entityManager->getDefinition($entityTypeId);

    $entityLabel = $entityDefinition->getLabel()->render();
    $id_key = $entityDefinition->getKey('id');
    $uuid_key = $entityDefinition->getKey('uuid');

    // Build the mapping for entity label + ID, e.g., 'Content ID' for nid.
    $entityIdLabel = $entityLabel . " ID";
    $mappings['reverse'][$entityIdLabel] = $id_key;

    // Build the mapping for entity label + UUID, e.g., 'Content UUID'
    // for uuid.
    $entityUuidLabel = $entityLabel . " UUID";
    $mappings['reverse'][$entityUuidLabel] = $uuid_key;


    foreach ($definitions as $fieldname => $definition) {
      $label = $definition->getLabel();
      //dpm("$fieldname => $label");
      if ($label instanceof TranslatableMarkup) {
        $label_string = $label->render();
      } else {
        $label_string = $label;
      }
      $mappings['forward'][$fieldname] = $label_string;
      $mappings['reverse'][$label_string] = $fieldname;
    }

    return $mappings;
  }

  /**
   * Helper function to do the table lookups.
   */
  protected function lookup($entityTypeId, $bundle, $needle, $direction) {
    // Check if this is the first time we've mapped the fields for this
    // type/bundle.
    if (empty($this->fieldMappings[$entityTypeId][$bundle])) {
      $this->fieldMappings[$entityTypeId][$bundle] = $this->buildFieldMappings($entityTypeId, $bundle);
    }

    // Check direct mapping first.
    if (isset($this->fieldMappings[$entityTypeId][$bundle][$direction][$needle])) {
      return $this->fieldMappings[$entityTypeId][$bundle][$direction][$needle];
    }

    // If we didn't find a mapping, return the original.
    return $needle;
  }

  /**
   * Return the field name given a field label/entity type id/bundle.
   */
  public function getEntityBaseKeyMapping($entityTypeId) {
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityTypeId);
    $entityDefinition = $this->entityManager->getDefinition($entityTypeId);
    $entityLabel = $entityDefinition->getLabel()->render();

    $mappings = [];
    $id_key = $entityDefinition->getKey('id');
    $uuid_key = $entityDefinition->getKey('uuid');
    $bundle_key = $entityDefinition->getKey('bundle');

    $id_label_definition = $definitions[$id_key]->getLabel();
    $id_label = $id_label_definition instanceof TranslatableMarkup ? $id_label_definition->render() : $id_label_definition;

    $uuid_label_definition = $definitions[$uuid_key]->getLabel();
    $uuid_label = $uuid_label_definition instanceof TranslatableMarkup ? $uuid_label_definition->render() : $uuid_label_definition;

    $bundle_label_definition = $definitions[$bundle_key]->getLabel();
    $bundle_label = $bundle_label_definition instanceof TranslatableMarkup ? $bundle_label_definition->render() : $bundle_label_definition;


    $mappings['id']['fieldname'] = $id_key;
    $mappings['id']['label'] = $entityLabel . " " . $id_label;
    $mappings['uuid']['fieldname'] = $uuid_key;
    $mappings['uuid']['label'] = $entityLabel . " " . $uuid_label;
    $mappings['bundle']['fieldname'] = $bundle_key;
    $mappings['bundle']['label'] = $bundle_label;

    return $mappings;
  }

  /**
   * Return the field name given a field label/entity type id/bundle.
   */
  public function getFieldname($entityTypeId, $bundle, $fieldLabel) {
    return $this->lookup($entityTypeId, $bundle, $fieldLabel, 'reverse');
  }

  /**
   * Return the field label given a field name/entity type id/bundle.
   */
  public function getLabel($entityTypeId, $bundle, $fieldname) {
    return $this->lookup($entityTypeId, $bundle, $fieldname, 'forward');
  }

  /**
   * Given a list of field labels (e.g., import sheet headers) return the entity type id.
   */
  public function entityTypeFromFieldLabels(array $fieldLabels) {
    $entity_definitions = $this->entityManager->getDefinitions();

    foreach ($entity_definitions as $id => $definition) {
      if ($definition->getGroup() !== 'content') {
        continue;
      }

      $entityLabel = $definition->getLabel()->render();
      $entityIdFields = [];
      $entityIdFields[] = $entityLabel . " ID";
      $entityIdFields[] = $entityLabel . " UUID";

      foreach ($entityIdFields as $label) {
        if (in_array($label, $fieldLabels)) {
          return $id;
        }
      }
    }

    return NULL;
  }

}
