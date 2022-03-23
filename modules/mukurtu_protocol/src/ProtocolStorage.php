<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

/**
 * Defines the storage handler class for Protocol entities.
 *
 * This extends the base storage class, adding required special handling for
 * Protocol entities.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolStorage extends SqlContentEntityStorage implements ProtocolStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(ProtocolInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {protocol_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {protocol_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(ProtocolInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {protocol_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('protocol_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
