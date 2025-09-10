<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\media\MediaInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Community entities.
 *
 * @ingroup mukurtu_protocol
 */
interface CommunityInterface extends MukurtuGroupInterface, ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

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
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setName($name);

  /**
   * Gets the Community description.
   *
   * @return string
   *   Description of the Community.
   */
  public function getDescription();

  /**
   * Sets the Community description.
   *
   * @param string $description
   *   The Community description.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setDescription($description);

  /**
   * Gets the Community type.
   *
   * @return null|\Drupal\taxonomy\Entity\Term
   *   The community type term or null.
   */
  public function getCommunityType();

  /**
   * Sets the Community type.
   *
   * @param int $community_type
   *   The community type term ID.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setCommunityType($community_type);

  /**
   * Gets the sharing setting.
   *
   * @return string
   *   Sharing setting machine name.
   */
  public function getSharingSetting();

  /**
   * Sets the sharing setting.
   *
   * @param string $sharing
   *   The sharing setting machine name.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   *   The called Community entity.
   */
  public function setSharingSetting($sharing);

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
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
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
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
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
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Get the parent community.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface|null
   *   The parent community entity.
   */
  public function getParentCommunity(): ?CommunityInterface;

  /**
   * Get the thumbnail image.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The thumbnail media.
   */
  public function getThumbnailImage(): ?MediaInterface;

  /**
   * Set the thumbnail image.
   *
   * @param \Drupal\media\MediaInterface $image
   *   The thumbnail image media entity.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setThumbnailImage(MediaInterface $image): CommunityInterface;

  /**
   * Get the banner image.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The banner media.
   */
  public function getBannerImage(): ?MediaInterface;

  /**
   * Set the banner image.
   *
   * @param \Drupal\media\MediaInterface $image
   *   The banner image media entity.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface
   *   The called Community entity.
   */
  public function setBannerImage(MediaInterface $image): CommunityInterface;

  /**
   * Get the child communities.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface[]
   *   The child community entities.
   */
  public function getChildCommunities();

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

  /**
   * Get the protocols.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   The protocol entities.
   */
  public function getProtocols();

  /**
   * Check if this community has a community manager.
   *
   * @return bool
   *   TRUE if the community has at least one community manager.
   */
  public function hasCommunityManager();

  /**
   * Check if a user in this community is a community manager.
   *
   * @param int $uid
   *   The user ID.
   * @return bool
   *   TRUE if the user is a community manager of this community, FALSE if the
   *   user is either not a member of this community or if the user does not
   *   have the community manager role.
   */
  public function isCommunityManager($uid);
}
