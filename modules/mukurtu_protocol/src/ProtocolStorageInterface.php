<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface ProtocolStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Protocol revision IDs for a specific Protocol.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $entity
   *   The Protocol entity.
   *
   * @return int[]
   *   Protocol revision IDs (in ascending order).
   */
  public function revisionIds(ProtocolInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Protocol author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Protocol revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $entity
   *   The Protocol entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(ProtocolInterface $entity);

  /**
   * Unsets the language for all Protocol with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
