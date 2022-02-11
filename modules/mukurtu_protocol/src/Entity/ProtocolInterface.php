<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Protocol entities.
 *
 * @ingroup mukurtu_protocol
 */
interface ProtocolInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Protocol name.
   *
   * @return string
   *   Name of the Protocol.
   */
  public function getName();

  /**
   * Sets the Protocol name.
   *
   * @param string $name
   *   The Protocol name.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   *   The called Protocol entity.
   */
  public function setName($name);

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
   *   The called Protocol entity.
   */
  public function setSharingSetting($sharing);

  /**
   * Check if this is a strict protocol.
   *
   * @return bool
   *   TRUE if strict FALSE otherwise.
   */
  public function isStrict() : bool;

  /**
   * Check if this is an open protocol.
   *
   * @return bool
   *   TRUE if open FALSE otherwise.
   */
  public function isOpen() : bool;

  /**
   * Get the communities this protocol belongs to.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface[]
   *   The community entities.
   */
  public function getCommunities();

  /**
   * Gets the Protocol creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Protocol.
   */
  public function getCreatedTime();

  /**
   * Sets the Protocol creation timestamp.
   *
   * @param int $timestamp
   *   The Protocol creation timestamp.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   *   The called Protocol entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Protocol revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Protocol revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   *   The called Protocol entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Protocol revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Protocol revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   *   The called Protocol entity.
   */
  public function setRevisionUserId($uid);

}
