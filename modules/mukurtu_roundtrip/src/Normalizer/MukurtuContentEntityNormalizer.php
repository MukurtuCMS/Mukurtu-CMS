<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer;

class MukurtuContentEntityNormalizer extends SerializerAwareNormalizer implements NormalizerInterface {
  use StringTranslationTrait;

  const FORMAT = 'csv';

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // TRUE = Use field labels as headers, FALSE = use field names as headers.
    $optionFieldLabelHeaders = $context['fieldLabelHeaders'] ?? TRUE;

    // Field names to omit from serialization.
    $omitFieldList = $context['omitFieldList'] ?? [];

    $flatten = function ($e) {
      return $e['value'] ?: '';
    };

    $normalized = [];

    foreach ($entity->getFields(TRUE) as $field_item_list) {
      // Skip omitted fields.
      if (in_array($field_item_list->getName(), $omitFieldList)) {
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
}
