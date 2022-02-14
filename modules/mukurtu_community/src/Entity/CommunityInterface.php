<?php

namespace Drupal\mukurtu_community\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Community entities.
 *
 * @ingroup mukurtu_community
 */
interface CommunityInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Community name.
   *
   * @return string
   *   Name of the Community.
   */
  public function getName();

  /**
   * Sets the Community name.
   *
   * @param string $name
   *   The Community name.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setName($name);

  /**
   * Gets the Community creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Community.
   */
  public function getCreatedTime();

  /**
   * Sets the Community creation timestamp.
   *
   * @param int $timestamp
   *   The Community creation timestamp.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Community revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Community revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Community revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Community revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Get the parent community.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface|null
   *   The parent community entity.
   */
  public function getParentCommunity(): ?CommunityInterface;

  /**
   * Set the parent community.
   *
   * @param \Drupal\mukurtu_community\Entity\CommunityInterface $community
   *   The parent community entity.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setParentCommunity(CommunityInterface $community): CommunityInterface;

  /**
   * Get the child communities.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface[]
   *   The child community entities.
   */
  public function getChildCommunities();

  /**
   * Set the child communities.
   *
   * @param \Drupal\mukurtu_community\Entity\CommunityInterface[] $communities
   *   The child community entities.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setChildCommunities(array $communities): CommunityInterface;

  /**
   * Check if the community has children.
   *
   * @return bool
   *   TRUE if the community has children.
   */
  public function isParentCommunity(): bool;

  /**
   * Check if the community has a parent.
   *
   * @return bool
   *   TRUE if the community has a parent community.
   */
  public function isChildCommunity(): bool;

}
