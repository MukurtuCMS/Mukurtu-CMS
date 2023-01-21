<?php

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\mukurtu_import\Exception\MukurtuEntityValidationException;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;
use Drupal\migrate\Exception\EntityValidationException;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
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
          // @todo Need to create our own version of this with a better message and ideally row number.
          throw new MukurtuEntityValidationException($pceViolations);
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

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []) {
    if ($entity instanceof RevisionLogInterface) {
      $message = $this->migration->pluginDefinition["mukurtu_import_message"] ?? '';
      $entity->setRevisionUserId(\Drupal::currentUser()->id());
      $entity->setNewRevision(TRUE);
      $entity->setRevisionLogMessage($message);
    }
    $entity->save();
    return [$entity->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function validateEntity(FieldableEntityInterface $entity) {
    // Entity validation can require the user that owns the entity. Switch to
    // use that user during validation.
    // As an example:
    // @see \Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint
    $account = $entity instanceof EntityOwnerInterface ? $entity->getOwner() : NULL;
    // Validate account exists as the owner reference could be invalid for any
    // number of reasons.
    if ($account) {
      $this->accountSwitcher->switchTo($account);
    }
    // This finally block ensures that the account is always switched back, even
    // if an exception was thrown. Any validation exceptions are intentionally
    // left unhandled. They should be caught and logged by a catch block
    // surrounding the row import and then added to the migration messages table
    // for the current row.
    try {
      $violations = $entity->validate();
    } finally {
      if ($account) {
        $this->accountSwitcher->switchBack();
      }
    }

    if (count($violations) > 0) {
      throw new MukurtuEntityValidationException($violations);
    }
  }

}
