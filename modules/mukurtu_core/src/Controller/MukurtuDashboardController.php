<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MukurtuDashboardController extends ControllerBase
{

  /**
   * Only authenticated users may access the dashboard.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access()
  {
    $account = \Drupal::currentUser();
    if (!$account->isAnonymous()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Redirects the bare /dashboard path to the Mukurtu dashboard.
   *
   * The dashboards module only routes /dashboard/{dashboard}, so a bookmarked
   * or typed /dashboard would otherwise 404 (#2391). The redirect is a 302,
   * not a 301, so browsers don't cache it permanently.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the mukurtu_dashboard dashboard.
   */
  public function redirectToDashboard()
  {
    return new RedirectResponse(Url::fromRoute('entity.dashboard.canonical', ['dashboard' => 'mukurtu_dashboard'])->toString());
  }
}
