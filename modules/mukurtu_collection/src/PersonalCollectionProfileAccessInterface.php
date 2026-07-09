<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Session\AccountInterface;

/**
 * Determines which personal collections a viewer may see on a user's profile.
 */
interface PersonalCollectionProfileAccessInterface {

  /**
   * Gets the IDs of personal collections belonging to a user's profile.
   *
   * When the viewer is the profile's owner, both public and private
   * published collections are returned. Otherwise, only public published
   * collections are returned.
   *
   * @param \Drupal\Core\Session\AccountInterface $viewer
   *   The user viewing the profile.
   * @param \Drupal\Core\Session\AccountInterface $profileUser
   *   The user whose profile is being viewed.
   *
   * @return int[]
   *   The viewable personal collection entity IDs, newest first.
   */
  public function getViewableCollectionIds(AccountInterface $viewer, AccountInterface $profileUser): array;

}
