<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\serialization\Normalizer\ComplexDataNormalizer;

class MukurtuEntityReferenceFieldItemNormalizer extends ComplexDataNormalizer {

  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\EntityReferenceFieldItemList';

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = [];

    foreach ($field_item as $item) {
      $values[]['value'] = $item->target_id;
    }

    return $values;
  }

}
