<?php

declare(strict_types=1);

namespace Drupal\mukurtu_tour;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;

/**
 * Tracks which tours a user has chosen to stop seeing.
 */
class TourDismissalManager {

  /**
   * Constructs a new TourDismissalManager.
   *
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected UserDataInterface $userData,
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Checks whether the current user has dismissed a tour.
   *
   * @param string $tour_id
   *   The tour config entity id.
   *
   * @return bool
   *   TRUE if the current user dismissed this tour.
   */
  public function isDismissed(string $tour_id): bool {
    $dismissed = $this->userData->get('mukurtu_tour', $this->currentUser->id(), 'dismissed_tours') ?? [];
    return in_array($tour_id, $dismissed, TRUE);
  }

  /**
   * Marks a tour as dismissed for the current user.
   *
   * @param string $tour_id
   *   The tour config entity id.
   */
  public function dismiss(string $tour_id): void {
    $dismissed = $this->userData->get('mukurtu_tour', $this->currentUser->id(), 'dismissed_tours') ?? [];
    if (!in_array($tour_id, $dismissed, TRUE)) {
      $dismissed[] = $tour_id;
      $this->userData->set('mukurtu_tour', $this->currentUser->id(), 'dismissed_tours', $dismissed);
    }
  }

}
