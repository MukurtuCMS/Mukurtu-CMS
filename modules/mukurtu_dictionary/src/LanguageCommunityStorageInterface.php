<?php

namespace Drupal\mukurtu_dictionary;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface LanguageCommunityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Language community revision IDs for a specific Language community.
   *
   * @param \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface $entity
   *   The Language community entity.
   *
   * @return int[]
   *   Language community revision IDs (in ascending order).
   */
  public function revisionIds(LanguageCommunityInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Language community author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Language community revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface $entity
   *   The Language community entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(LanguageCommunityInterface $entity);

  /**
   * Unsets the language for all Language community with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
