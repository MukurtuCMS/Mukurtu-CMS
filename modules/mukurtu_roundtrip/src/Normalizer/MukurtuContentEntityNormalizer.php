<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class MukurtuContentEntityNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface {
  use StringTranslationTrait;

  const FORMAT = 'csv';

  protected $fieldMappings;

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // TRUE = Use field labels as headers, FALSE = use field names as headers.
    $optionFieldLabelHeaders = $context['fieldLabelHeaders'] ?? TRUE;

    // Field names we are always going to omit from Mukurtu CSV serialization.
    $alwaysOmitFieldList = [
      'created',
      'changed',
      'path',
      'revision_timestamp',
      'revision_uid',
      'revision_default',
      'vid',
      'revision_log',
      'revision_translation_affected',
      'langcode',
      'default_langcode',
    ];

    // Optional field names to omit from serialization.
    $omitFieldList = $context['omitFieldList'] ?? [];

    $flatten = function ($e) {
      return $e['value'] ?: '';
    };

    $normalized = [];

    foreach ($entity->getFields(TRUE) as $field_item_list) {
      // Skip omitted fields.
      if (in_array($field_item_list->getName(), $alwaysOmitFieldList) || in_array($field_item_list->getName(), $omitFieldList)) {
        continue;
      }
      $label = $optionFieldLabelHeaders ? (string) $field_item_list->getFieldDefinition()->getLabel() : $field_item_list->getName();
      $normalized_field_item = $this->serializer->normalize($field_item_list, $format, $context);

      $normalized[$label] = array_map($flatten, $normalized_field_item) ?? '';
    }

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $format === static::FORMAT && $data instanceof ContentEntityInterface;
  }

  protected function mapRow($headers, $row) {
    $mappedRow = [];
    foreach ($headers as $delta => $fieldName) {
      $mappedRow[$headers[$delta]] = $row[$delta];
    }
    return $mappedRow;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $fieldResolver = \Drupal::service('mukurtu_roundtrip.import_fieldname_resolver');
    $use_headers = TRUE;
    $headers = [];
    $entities = [];
    ///dpm($data);
    $entityManager = \Drupal::entityTypeManager();
    $entity_definitions = $entityManager->getDefinitions();
    //dpm($entity_definitions);

    $entity_type_id = NULL;
    $entity_type_definition = NULL;
    foreach ($entity_definitions as $id => $definiton) {
      if ($definiton->getOriginalClass() == $class) {
        $entity_type_id = $id;
        $entity_type_definition = $definiton;
        break;
      }
    }

    // Don't try to create an entity without an entity type id.
    if (!$entity_type_definition) {
      throw new UnexpectedValueException(sprintf('The specified entity type "%s" does not exist. A valid entity type is required for denormalization', $entity_type_id));
    }

    $key_id = $entity_type_definition->getKey('id');
    $key_bundle = $entity_type_definition->getKey('bundle');
    $key_uuid = $entity_type_definition->getKey('uuid');

    foreach ($data as $rawRow) {
      // Grab the headers.
      if ($use_headers && empty($headers)) {
        $headers = $rawRow;
        continue;
      }

      //dpm($headers);
      //dpm($rawRow);
      $row = $this->mapRow($headers, $rawRow);
      //dpm($row);
      $entity = $this->getEntity($row, $entity_type_id, $key_bundle, $key_id, $key_uuid);
      //dpm($entity);

      foreach ($row as $field_header_name => $field_data) {
        if ($field_data) {
          // Resolve header value to field name.
          $field_name = $fieldResolver->getFieldname($entity_type_id, $entity->bundle(), $field_header_name);

          $items = $entity->get($field_name);
          $items->setValue([]);
          $field_data = is_array($field_data) ? $field_data : [$field_data];
          // Denormalize the field data into the FieldItemList object.
          $context['target_instance'] = $items;
          $field = $this->serializer->denormalize($field_data, get_class($items), $format, $context);
          //dpm($field);
          if ($field) {
            $entity->{$field_name} = $field;
          }
        }
      }

      $entities[] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $format === static::FORMAT && $type == 'Drupal\node\Entity\Node';
  }

  /**
   * Load the existing entity or create a new entity if it doesn't exist.
   */
  protected function getEntity($row, $entity_type_id, $bundle_key, $id_key, $uuid_key) {
    // Try loading by ID first.
    //$id = !empty($row['ID']) ? $row['ID'] : (!empty($row['id']) ? $row['id'] : NULL);
    $id = !empty($row[$id_key]) ? $row[$id_key] : NULL;
    if ($id !== NULL) {
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id);
      if ($entity) {
        return $entity;
      }
    }

    // Try loading by UUID next.
    //$uuid = !empty($row['UUID']) ? $row['UUID'] : (!empty($row['uuid']) ? $row['uuid'] : NULL);
    $uuid = !empty($row[$uuid_key]) ? $row[$uuid_key] : NULL;
    if ($uuid !== NULL) {
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type_id, $uuid);
      if ($entity) {
        return $entity;
      }
    }

    // Create a new entity.
    $type = !empty($row[$bundle_key]) ? $row[$bundle_key] : NULL;
    if ($type === NULL) {
      $msg = implode(', ', $row);
      throw new UnexpectedValueException("Could not determine the bundle type during normalization/denormalization for row: $msg");
    }
    $values = [$bundle_key => $type];
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->create($values);

    return $entity;
  }

}
