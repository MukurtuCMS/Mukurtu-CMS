<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Language community entities.
 *
 * @ingroup mukurtu_dictionary
 */
interface LanguageCommunityInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Language community name.
   *
   * @return string
   *   Name of the Language community.
   */
  public function getName();

  /**
   * Sets the Language community name.
   *
   * @param string $name
   *   The Language community name.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface
   *   The called Language community entity.
   */
  public function setName($name);

  /**
   * Gets the Language community creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Language community.
   */
  public function getCreatedTime();

  /**
   * Sets the Language community creation timestamp.
   *
   * @param int $timestamp
   *   The Language community creation timestamp.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface
   *   The called Language community entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Language community revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Language community revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface
   *   The called Language community entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Language community revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Language community revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\LanguageCommunityInterface
   *   The called Language community entity.
   */
  public function setRevisionUserId($uid);

}
