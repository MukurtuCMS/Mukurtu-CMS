<?php

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;
use Drupal\migrate\Exception\EntityValidationException;
use Exception;

class ProtocolAwareEntityContent extends EntityContentBase {
  public function import(Row $row, array $old_destination_id_values = []) {
    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity) {
      throw new MigrateException('Unable to get entity');
    }
    assert($entity instanceof ContentEntityInterface);

    // Protocol Control.
    if (ProtocolControl::supportsProtocolControl($entity)) {
      $pce = ProtocolControl::getProtocolControlEntity($entity) ?? ProtocolControl::create([]);
      $savePce = FALSE;
      // Sharing setting, any/all.
      if ($row->hasDestinationProperty('field_protocol_control:field_sharing_setting')) {
        $pce->setPrivacySetting($row->getDestinationProperty('field_protocol_control:field_sharing_setting'));
        $savePce = TRUE;
      }
      // Protocol list.
      if ($row->hasDestinationProperty('field_protocol_control:field_protocols')) {
        $pce->setProtocols($row->getDestinationProperty('field_protocol_control:field_protocols'));
        $savePce = TRUE;
      }

      // Save the protocol control entity if valid.
      if ($savePce) {
        $pceViolations = $pce->validate();
        if (count($pceViolations) > 0) {
          throw new EntityValidationException($pceViolations);
        }
        try {
          $pce->save();
        } catch (Exception $e) {
          throw new MigrateException('Unable to save protocol control entity');
        }
      }
    }

    if ($this->isEntityValidationRequired($entity)) {
      $this->validateEntity($entity);
    }
    $ids = $this->save($entity, $old_destination_id_values);
    if ($this->isTranslationDestination()) {
      $ids[] = $entity->language()->getId();
    }
    return $ids;
  }

}
