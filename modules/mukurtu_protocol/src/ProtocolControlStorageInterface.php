<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface ProtocolControlStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Protocol control revision IDs for a specific Protocol control.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity
   *   The Protocol control entity.
   *
   * @return int[]
   *   Protocol control revision IDs (in ascending order).
   */
  public function revisionIds(ProtocolControlInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Protocol control author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Protocol control revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

}
