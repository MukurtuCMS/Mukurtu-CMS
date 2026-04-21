<?php

namespace Drupal\search_api_solr_admin\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrBackendInterface;

/**
 * Provides an access check for the "Solr Admin" routes.
 */
class SolrAdminAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   (optional) The Search API server entity.
   */
  public function access(AccountInterface $account, ?ServerInterface $search_api_server = NULL) {
    if ($search_api_server) {
      $backend = $search_api_server->getBackend();
      if ($backend instanceof SolrBackendInterface) {
        if (!$backend->getSolrConnector()->isCloud()) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden();
  }

}
