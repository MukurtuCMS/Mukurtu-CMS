<?php

namespace Drupal\mukurtu_protocol\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
//use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\Core\TypedData\ComputedItemListTrait;

class MukurtuCalculatedProtocolField extends FieldItemList implements FieldItemListInterface {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $fieldValues = [];

    $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
    $protocolScope = $entity->get(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE)->value;
    $protocols = $protocol_manager->getProtocols($entity);

    if ($protocolScope == MUKURTU_PROTOCOL_PUBLIC) {
      $fieldValues[] = $protocol_manager->getProtocolGrantId([], 'public');
    }

    if ($protocolScope == MUKURTU_PROTOCOL_ALL) {
      $fieldValues[] = $protocol_manager->getProtocolGrantId($protocols);
    }

    if ($protocolScope == MUKURTU_PROTOCOL_ANY) {
      foreach ($protocols as $protocol) {
        $fieldValues[] = $protocol_manager->getProtocolGrantId([$protocol]);
      }
    }

    // Personal.
    if (empty($fieldValues)) {
      $fieldValues[] = $protocol_manager->getProtocolGrantId([$entity->getOwnerId()], 'user');
    }

    foreach ($fieldValues as $delta => $fieldValue) {
      $this->list[$delta] = $this->createItem($delta, $fieldValue);
    }

    dpm($fieldValues);
  }

}
