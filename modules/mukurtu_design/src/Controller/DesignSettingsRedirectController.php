<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects the old colour settings path to the design settings form.
 *
 * The form lived at /admin/config/color-settings until it gained background
 * image settings, at which point the path described only half of what it did.
 * Bookmarks and docs.mukurtu.org still point at the old URL, so it moves
 * permanently rather than disappearing.
 */
class DesignSettingsRedirectController extends ControllerBase {

  /**
   * Redirects to the design settings form.
   */
  public function settings(): RedirectResponse {
    return new RedirectResponse(
      Url::fromRoute('mukurtu_design.settings')->toString(),
      RedirectResponse::HTTP_MOVED_PERMANENTLY
    );
  }

}
