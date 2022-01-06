<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface PersonalCollectionStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Personal collection revision IDs for a specific Personal collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface $entity
   *   The Personal collection entity.
   *
   * @return int[]
   *   Personal collection revision IDs (in ascending order).
   */
  public function revisionIds(PersonalCollectionInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Personal collection author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Personal collection revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface $entity
   *   The Personal collection entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(PersonalCollectionInterface $entity);

  /**
   * Unsets the language for all Personal collection with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
