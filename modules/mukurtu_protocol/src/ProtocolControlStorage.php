<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControlInterface;

/**
 * Defines the storage handler class for Protocol control entities.
 *
 * This extends the base storage class, adding required special handling for
 * Protocol control entities.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolControlStorage extends SqlContentEntityStorage implements ProtocolControlStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(ProtocolControlInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {protocol_control_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {protocol_control_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

}
