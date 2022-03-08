<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for defining Protocol control entities.
 *
 * @ingroup mukurtu_protocol
 */
interface ProtocolControlInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Protocol control name.
   *
   * @return string
   *   Name of the Protocol control.
   */
  public function getName();

  /**
   * Sets the Protocol control name.
   *
   * @param string $name
   *   The Protocol control name.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The called Protocol control entity.
   */
  public function setName($name);

  /**
   * Gets the Protocol control creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Protocol control.
   */
  public function getCreatedTime();

  /**
   * Sets the Protocol control creation timestamp.
   *
   * @param int $timestamp
   *   The Protocol control creation timestamp.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The called Protocol control entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Protocol control revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Protocol control revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The called Protocol control entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Protocol control revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Protocol control revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The called Protocol control entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Gets the Protocol control sharing setting.
   *
   * @return string
   *   The sharing setting ID.
   */
  public function getPrivacySetting();

  /**
   * Sets the Protocol control sharing setting.
   *
   * @param string $option
   *   The ID of the sharing setting.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The called Protocol control entity.
   */
  public function setPrivacySetting($option);

  /**
   * Gets the Protocol control sharing options.
   *
   * @return mixed
   *   The sharing setting options array.
   */
  public function getPrivacySettingOptions();

  /**
   * Get the Protocol control protocols.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   An array of protocols.
   */
  public function getProtocols();

  /**
   * Set the protocol control protocols.
   *
   * @param mixed $protocols
   *   An array of protocol IDs.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The protocol control interface.
   */
  public function setProtocols($protocols);
  /**
   * Sets the target entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target entity.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The protocol control interface.
   */
  public function setControlledEntity(EntityInterface $entity);

  /**
   * Get the entity this PCE controls.
   *
   * @return \Drupal\Core\Entity\EntityIterface
   *   The entity that uses this protocol control.
   */
  public function getControlledEntity();

  /**
   * Find the protocols (from the PC entity) the user is a member of.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   (optional) The user object. If empty the current user will be used.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   The protocols.
   */
  public function getMemberProtocols(?AccountInterface $user = NULL): array;

  /**
   * Check if a user is in the PC group.
   *
   * @return bool
   *   If the user is in the group.
   */
  public function inGroup(AccountInterface $user): bool;

  /**
   * Check if a user is in all protocols in the PCE.
   *
   * @return bool
   *   If the user is in all groups.
   */
  public function inAllGroups(AccountInterface $user): bool;

  /**
   * Get the community affiliations.
   *
   * @return \Drupal\mukurtu_community\Entity\CommunityInterface[]
   *   The communities.
   */
  public function getCommunities();

  /**
   * Get the protocol control entity for an entity under protocol control.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   *   The protocol control interface.
   */
  public static function getProtocolControlEntity(EntityInterface $entity);

  /**
   * Get the protocol set ID.
   *
   * @return int
   *   The protocol set ID.
   */
  public function getProtocolSetId();

  /**
   * Return the node access grants.
   *
   * @return mixed
   *   The grants array.
   */
  public function getNodeAccessGrants();

}
