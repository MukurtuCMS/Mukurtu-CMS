<?php

namespace Drupal\search_api;

/**
 * Represents a piece of configuration that was not permanently saved yet.
 */
interface UnsavedConfigurationInterface {

  /**
   * Sets the current user ID.
   *
   * @param int|string $current_user_id
   *   The UID of the currently logged-in user, or the session ID (for anonymous
   *   users).
   */
  public function setCurrentUserId($current_user_id);

  /**
   * Determines if there are any unsaved changes in this configuration.
   *
   * @return bool
   *   TRUE if any changes have been made to this configuration compared to the
   *   one in permanent storage; FALSE otherwise.
   */
  public function hasChanges();

  /**
   * Determines whether this configuration was saved by a different user.
   *
   * @return bool
   *   TRUE if a user not equal to the current one created this temporary
   *   configuration state and editing by the current user should therefore be
   *   forbidden.
   */
  public function isLocked();

  /**
   * Retrieves the owner of the lock on this configuration, if any.
   *
   * @return \Drupal\user\UserInterface|null
   *   The lock's owner; or NULL if this object represents the still unchanged
   *   configuration that is currently stored.
   */
  public function getLockOwner();

  /**
   * Retrieves the last updated date of this configuration, if any.
   *
   * @return int|null
   *   The time of the last change to this configuration; or NULL if this object
   *   represents the still unchanged configuration that is currently stored.
   */
  public function getLastUpdated();

  /**
   * Sets the lock information for this configuration.
   *
   * @param object|null $lock
   *   The lock information, as an object with properties "owner" and "updated";
   *   or NULL if this object represents the still unchanged configuration that
   *   is currently stored.
   *
   * @return $this
   */
  public function setLockInformation($lock = NULL);

  /**
   * Saves the changes represented by this object permanently.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" entity type doesn't exist.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if saving the index failed.
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the temp storage could not be acquired.
   */
  public function savePermanent();

  /**
   * Discards the changes represented by this object.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the temp storage could not be acquired.
   */
  public function discardChanges();

}
