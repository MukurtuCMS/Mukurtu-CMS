<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\mukurtu_collection\Entity\PersonalCollectionInterface;

/**
 * Defines the storage handler class for Personal collection entities.
 *
 * This extends the base storage class, adding required special handling for
 * Personal collection entities.
 *
 * @ingroup mukurtu_collection
 */
class PersonalCollectionStorage extends SqlContentEntityStorage implements PersonalCollectionStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(PersonalCollectionInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {personal_collection_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {personal_collection_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(PersonalCollectionInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {personal_collection_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('personal_collection_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
