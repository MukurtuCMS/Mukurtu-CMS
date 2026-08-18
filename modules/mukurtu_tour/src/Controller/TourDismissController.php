<?php

declare(strict_types=1);

namespace Drupal\mukurtu_tour\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_tour\TourDismissalManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles per-user tour dismissal.
 */
class TourDismissController extends ControllerBase {

  public function __construct(protected TourDismissalManager $dismissalManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('mukurtu_tour.dismissal_manager'));
  }

  /**
   * Dismisses a tour for the current user.
   *
   * @param string $tour
   *   The tour config entity id.
   */
  public function dismiss(string $tour): RedirectResponse {
    $this->dismissalManager->dismiss($tour);
    $this->messenger()->addStatus($this->t('This tour will no longer be shown.'));
    return $this->redirect('<front>');
  }

}
