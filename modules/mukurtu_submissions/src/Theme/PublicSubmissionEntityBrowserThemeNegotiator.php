<?php

namespace Drupal\mukurtu_submissions\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps entity browser modals on the front-end theme for public submitters.
 *
 * A handful of shipped entity browsers (mukurtu_content_browser,
 * mukurtu_dictionary_word_browser) are reachable both from normal
 * admin-theme content-editing forms (node/add, node/*\/edit) and from the
 * anonymous public submission form (/submit/{entity_type_id}/{bundle} -
 * see PublicSubmissionForm), via fields like Collection's
 * field_items_in_collection and Word List's field_words.
 *
 * Entity Browser's Modal display renders in its own separate route
 * (EntityBrowser::route()), whose _admin_route flag is a single static
 * per-browser setting - it can't vary by which page opened the modal. So
 * flagging those browsers admin-themed (to fix them for editors, see #2039)
 * would incorrectly push Gin onto the same modal for an anonymous visitor
 * on a public submission page, which must stay on the front-end theme.
 *
 * The modal route always carries the host page's path as an
 * 'original_path' query parameter (see Modal::displayEntityBrowser()) -
 * use that to detect this case and force the front-end theme, regardless
 * of the browser's own use_admin_theme setting or the current user's
 * permissions.
 */
class PublicSubmissionEntityBrowserThemeNegotiator implements ThemeNegotiatorInterface {

  public function __construct(
    protected RequestStack $requestStack,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route_name = $route_match->getRouteName();
    if ($route_name === NULL || !str_starts_with($route_name, 'entity_browser.')) {
      return FALSE;
    }

    $request = $this->requestStack->getCurrentRequest();
    $original_path = $request?->query->get('original_path');
    return is_string($original_path) && str_starts_with($original_path, '/submit/');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->configFactory->get('system.theme')->get('default');
  }

}
