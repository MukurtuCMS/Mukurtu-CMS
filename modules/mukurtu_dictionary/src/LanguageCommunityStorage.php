<?php

namespace Drupal\mukurtu_dictionary;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface;

/**
 * Defines the storage handler class for Language community entities.
 *
 * This extends the base storage class, adding required special handling for
 * Language community entities.
 *
 * @ingroup mukurtu_dictionary
 */
class LanguageCommunityStorage extends SqlContentEntityStorage implements LanguageCommunityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(LanguageCommunityInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {language_community_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {language_community_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(LanguageCommunityInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {language_community_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('language_community_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
