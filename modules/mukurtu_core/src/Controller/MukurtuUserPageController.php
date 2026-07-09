<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MukurtuUserPageController extends ControllerBase
{

  /**
   * Redirects anonymous visitors to the login form and logged-in users to
   * their own profile.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function userPage()
  {
    if ($this->currentUser()->isAnonymous()) {
      $url = Url::fromRoute('user.login', [], ['query' => $this->getDestinationArray()]);
      return new RedirectResponse($url->toString());
    }

    return $this->redirect('entity.user.canonical', ['user' => $this->currentUser()->id()]);
  }
}
