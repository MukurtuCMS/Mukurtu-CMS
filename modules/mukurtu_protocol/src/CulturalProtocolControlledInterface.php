<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;

interface CulturalProtocolControlledInterface {
  /**
   * Return the base field definitions for the protocol fields.
   *
   * @return array
   *   The field definitions.
   */
  public static function getProtocolFieldDefinitions(): array;

  /**
   * Gets the entity sharing setting.
   *
   * @return string
   *   The sharing setting ID.
   */
  public function getSharingSetting(): string;

  /**
   * Sets the entity sharing setting.
   *
   * @param string $option
   *   The ID of the sharing setting.
   *
   * @return \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface
   *   The entity.
   */
  public function setSharingSetting($option): CulturalProtocolControlledInterface;

  /**
   * Gets the sharing options.
   *
   * @return mixed
   *   The sharing options array.
   */
  //public function getPrivacySettingOptions();

  /**
   * Get the entity's protocols.
   *
   * @return int[]
   *   An array of protocols IDs.
   */
  public function getProtocols();

  /**
   * Get the entity's protocol entities.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   An array of protocols entities.
   */
  public function getProtocolEntities();

  /**
   * Get the entity's affiliated communities.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface[]
   *   An array of community entities.
   */
  public function getCommunities();

  /**
   * Set the entity's protocols.
   *
   * @param mixed $protocols
   *   An array of protocol IDs.
   *
   * @return \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface
   *   The entity.
   */
  public function setProtocols($protocols);


  /**
   * Get the entity's protocols the user is a member of.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   (optional) The user object. If empty the current user will be used.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   The entity's protocol entities that the user is a member of.
   */
  public function getMemberProtocols(?AccountInterface $user = NULL): array;

  /**
   * Check if a user is a member of the protocol set.
   *
   * @return bool
   *   True if the user is a member of the set.
   */
  public function isProtocolSetMember(AccountInterface $user): bool;

  /**
   * Get the access grants for the entity.
   *
   * @return array
   */
  public function getAccessGrants(): array;

  /**
   * Build the Mukurtu protocol set grants for non-nodes.
   */
  public function buildAccessGrants(): void;

  /**
   * Remove the Mukurtu protocol grants for an entity.
   */
  public function removeAccessGrants(): void;

}
