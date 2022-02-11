<?php

namespace Drupal\mukurtu_community;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\mukurtu_community\Entity\CommunityInterface;

/**
 * Defines the storage handler class for Community entities.
 *
 * This extends the base storage class, adding required special handling for
 * Community entities.
 *
 * @ingroup mukurtu_community
 */
class CommunityStorage extends SqlContentEntityStorage implements CommunityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(CommunityInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {community_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {community_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(CommunityInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {community_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('community_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
