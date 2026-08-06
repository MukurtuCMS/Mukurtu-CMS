<?php

declare(strict_types=1);

namespace Drupal\mukurtu_tour\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mukurtu_tour\TourDismissalManager;
use Drupal\tour\TourInterface;

/**
 * Hook implementations for the Mukurtu Tour module.
 */
final class MukurtuTourHooks {

  /**
   * Permissions required to see a given tour, keyed by tour id.
   *
   * Stub for exploring per-tour audience targeting: a tour listed here is
   * hidden from any viewer who lacks the mapped permission, even though core
   * Tour would otherwise show it to everyone with 'access tour'.
   */
  protected const TOUR_PERMISSIONS = [
    'mukurtu_creator' => 'create digital_heritage content',
  ];

  /**
   * Constructs a new MukurtuTourHooks.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\mukurtu_tour\TourDismissalManager $dismissalManager
   *   The tour dismissal manager.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected TourDismissalManager $dismissalManager,
  ) {
  }

  /**
   * Suppresses tours the current viewer has dismissed or shouldn't see.
   */
  #[Hook('tour_tips_alter')]
  public function tourTipsAlter(array &$tips, TourInterface $tour): void {
    if ($this->dismissalManager->isDismissed($tour->id())) {
      $tips = [];
      return;
    }

    $required_permission = self::TOUR_PERMISSIONS[$tour->id()] ?? NULL;
    if ($required_permission && !$this->currentUser->hasPermission($required_permission)) {
      $tips = [];
    }
  }

}
