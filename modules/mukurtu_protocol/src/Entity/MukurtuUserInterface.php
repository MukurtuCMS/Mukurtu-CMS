<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\user\UserInterface;

interface MukurtuUserInterface extends UserInterface {
  /**
   * Get the communities a user is a member of.
   *
   * @return \Drupal\mukurtu_protocol\Entity\CommunityInterface[]
   *   The community entities.
   */
  public function getCommunities();

  /**
   * Get the cultural protocols a user is a member of.
   *
   * @return \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   *   The protocol entities.
   */
  public function getProtocols();

}
