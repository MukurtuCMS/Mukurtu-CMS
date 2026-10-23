<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class MukurtuManageContentController extends ControllerBase {

  /**
   * Check access for adding new community records.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = \Drupal::currentUser();
    if (!$account->isAnonymous()) {
      return AccessResult::allowedIfHasPermission($account, 'administer nodes');
    }

    return AccessResult::forbidden();
  }

  public function addPage() {
    $build = [
      '#theme' => 'mukurtu_node_add_list',
      '#cache' => [
        'tags' => $this->entityTypeManager()->getDefinition('node_type')->getListCacheTags(),
      ],
    ];

    $content = [];

    // Only use node types the user has access to.
    foreach ($this->entityTypeManager()->getStorage('node_type')->loadMultiple() as $type) {
      $access = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess($type->id(), NULL, [], TRUE);
      if ($access->isAllowed()) {
        $content[$type->id()] = $type;
      }
    }

    // Bypass the node/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('node.add', ['node_type' => $type->id()]);
    }

    $build['#content'] = $content;

    return $build;
  }

  /**
   * Redirects the old /admin/node/add picker to core's /node/add (#2399).
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the content type picker.
   */
  public function redirectToAddPage(Request $request) {
    return $this->redirectToCoreRoute($request, 'node.add_page');
  }

  /**
   * Redirects the old /admin/node/add/{node_type} form to core's (#2399).
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The content type being added.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the add form for the content type.
   */
  public function redirectToAddForm(NodeTypeInterface $node_type, Request $request) {
    return $this->redirectToCoreRoute($request, 'node.add', ['node_type' => $node_type->id()]);
  }

  /**
   * Builds a redirect to a core node add route, keeping the query string.
   *
   * Core's redirect response subscriber would otherwise send the user straight
   * to a ?destination= value and skip the form. Keeping the query string lets
   * the form itself honor ?destination= after saving. The redirect is a 302,
   * not a 301, so browsers don't cache it permanently.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $route_name
   *   The core route to redirect to.
   * @param array $route_parameters
   *   The route parameters.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect.
   */
  protected function redirectToCoreRoute(Request $request, string $route_name, array $route_parameters = []) {
    \Drupal::service('redirect_response_subscriber')->setIgnoreDestination();
    $url = Url::fromRoute($route_name, $route_parameters, ['query' => $request->query->all()]);
    return new RedirectResponse($url->toString());
  }

}
