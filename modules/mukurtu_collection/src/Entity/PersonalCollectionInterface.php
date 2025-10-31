<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\mukurtu_collection\Entity\CollectionInterface;

/**
 * Provides an interface for defining Personal collection entities.
 *
 * @ingroup mukurtu_collection
 */
interface PersonalCollectionInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface, CollectionInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Personal collection name.
   *
   * @return string
   *   Name of the Personal collection.
   */
  public function getName();

  /**
   * Sets the Personal collection name.
   *
   * @param string $name
   *   The Personal collection name.
   *
   * @return \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   *   The called Personal collection entity.
   */
  public function setName($name);

  /**
   * Gets the Personal collection creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Personal collection.
   */
  public function getCreatedTime();

  /**
   * Sets the Personal collection creation timestamp.
   *
   * @param int $timestamp
   *   The Personal collection creation timestamp.
   *
   * @return \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   *   The called Personal collection entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Personal collection revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Personal collection revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   *   The called Personal collection entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Personal collection revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Personal collection revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   *   The called Personal collection entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Gets the Personal collection privacy setting.
   *
   * @return string
   *   The privacy setting key.
   */
  public function getPrivacy();

  /**
   * Sets the Personal collection privacy setting.
   *
   * @param string $privacy
   *   The privacy setting key.
   *
   * @return \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   *   The called Personal collection entity.
   */
  public function setPrivacy($privacy);

  /**
   * Is the personal collection set to private?
   *
   * @return bool
   *   True if private.
   */
  public function isPrivate(): bool;

}
