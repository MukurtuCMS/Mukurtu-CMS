<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MukurtuEntityReferenceFieldItemNormalizer extends ComplexDataNormalizer implements NormalizerInterface, DenormalizerInterface {

  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\EntityReferenceFieldItemList';

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return parent::supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    if ($format == 'csv') {
      $values = [];

      foreach ($field_item as $item) {
        $values[]['value'] = $item->target_id;
      }
    } else {
      $values = parent::normalize($field_item, $format, $context);
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return parent::supportsDenormalization($data, $type, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['target_instance'])) {
      throw new InvalidArgumentException('$context[\'target_instance\'] must be set to denormalize with the MukurtuEntityReferenceFieldItemNormalizer');
    }
    if ($context['target_instance']->getParent() == NULL) {
      throw new InvalidArgumentException('The field item passed in via $context[\'target_instance\'] must have a parent set.');
    }
    $field_item = $context['target_instance'];

    $refList = [];

    foreach ($data as $ref) {
      if (isset($ref['target_id'])) {
        $refList[] = ['target_id' => $ref['target_id']];
      }
      if (isset($ref['value'])) {
        $refList[] = ['target_id' => $ref['value']];
      }
    }

    $field_item->setValue($refList);

    return $field_item;
  }
}
