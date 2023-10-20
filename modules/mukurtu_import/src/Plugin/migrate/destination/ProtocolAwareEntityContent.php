<?php

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\migrate\Exception\EntityValidationException;

class ProtocolAwareEntityContent extends EntityContentBase {
  public function import(Row $row, array $old_destination_id_values = []) {
    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity) {
      throw new MigrateException('Unable to get entity');
    }
    assert($entity instanceof ContentEntityInterface);

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
  protected function getEntityId(Row $row) {
    // ID get priority.
    if ($id = $row->getDestinationProperty($this->getKey('id'))) {
      return $id;
    }

    // UUID is next.
    if ($uuid = $row->getDestinationProperty($this->getKey('uuid'))) {
      // Need to lookup the ID from the UUID.
      $destination_entity_type_id = $this->getPluginDefinition()['provider'] ?? NULL;
      if ($destination_entity_type_id) {
        return $this->getEntityIDfromUUID($destination_entity_type_id, $uuid);
      }
    }

    // @todo Should this be null or an exception?
    return NULL;
  }

  /**
   * Gets the entity ID from its UUID.
   *
   * @param string $entity_type_id
   *   The entity type ID (e.g., 'node', 'user', 'taxonomy_term').
   * @param string $uuid
   *   The UUID of the entity.
   *
   * @return int|null
   *   The entity ID or NULL if not found.
   */
  protected function getEntityIDfromUUID($entity_type_id, $uuid) {
    $entities = \Drupal::entityTypeManager()->getStorage($entity_type_id)->loadByProperties(['uuid' => $uuid]);
    $entity = reset($entities);
    return $entity ? $entity->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validateEntity(FieldableEntityInterface $entity) {
    // EntityContentBase uses the accountSwitcher to switch to the
    // owner account. We don't want to do that. For the Mukurtu
    // importer the user doing the import is the content creator
    // and all checks should be run using their account.
    try {
      $violations = $entity->validate();
    } finally {
      // Intentionally left blank.
    }

    if (count($violations) > 0) {
      throw new EntityValidationException($violations);
    }
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

}
