<?php

namespace Drupal\message\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;

/**
 * Message template destination plugin.
 *
 * @MigrateDestination(
 *   id = "entity:message_template"
 * )
 */
class MessageTemplateDestination extends EntityConfigBase {

  /**
   * {@inheritdoc}
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    $ret = parent::updateEntity($entity, $row);
    if ($row->getDestinationProperty('text')) {
      $entity->set('text', $row->getDestinationProperty('text'));
    }

    return $ret;
  }

}
