<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class MukurtuContentEntityNormalizer extends SerializerAwareNormalizer implements NormalizerInterface, DenormalizerInterface {
  use StringTranslationTrait;

  const FORMAT = 'csv';

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
    $use_headers = TRUE;
    $headers = [];
    $entities = [];

    foreach ($data as $rawRow) {
      // Grab the headers.
      if ($use_headers && empty($headers)) {
        $headers = $rawRow;
        continue;
      }

      $row = $this->mapRow($headers, $rawRow);
      $entity = $this->getEntity($row);

      foreach ($row as $field_name => $field_data) {
        if ($field_data) {
          $items = $entity->get($field_name);
          $items->setValue([]);
          $field_data = is_array($field_data) ? $field_data : [$field_data];
          // Denormalize the field data into the FieldItemList object.
          $context['target_instance'] = $items;
          $field = $this->serializer->denormalize($field_data, get_class($items), $format, $context);
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
   * Given a deserialized CSV row for an Entity, return the most likely bundle.
   */
  protected function inferEntityType($row) {
    $type = $row['type'] ?? '';
    return $type;
  }

  /**
   * Load the existing entity or create a new entity if it doesn't exist.
   */
  protected function getEntity($row) {
    // Try loading by NID first.
    if (!empty($row['nid'])) {
      $entity = \Drupal::entityTypeManager()->getStorage('node')->load($row['nid']);
      if ($entity) {
        return $entity;
      }
    }

    // Try loading by UUID next.
    if (!empty($row['uuid'])) {
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid('node', $row['uuid']);
      if ($entity) {
        return $entity;
      }
    }

    // Create a new entity.
    $type = $this->inferEntityType($row);
    if (!$type) {
      $msg = implode(', ', $row);
      throw new UnexpectedValueException("Could not determine the bundle type during normalization/denormalization for row: $msg");
    }
    $values = ['type' => $type];
    $entity = \Drupal::entityTypeManager()->getStorage('node')->create($values);

    return $entity;
  }

}
