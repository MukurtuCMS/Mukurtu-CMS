<?php

namespace Drupal\mukurtu_community;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface CommunityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Community revision IDs for a specific Community.
   *
   * @param \Drupal\mukurtu_community\Entity\CommunityInterface $entity
   *   The Community entity.
   *
   * @return int[]
   *   Community revision IDs (in ascending order).
   */
  public function revisionIds(CommunityInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Community author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Community revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\mukurtu_community\Entity\CommunityInterface $entity
   *   The Community entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(CommunityInterface $entity);

  /**
   * Unsets the language for all Community with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
