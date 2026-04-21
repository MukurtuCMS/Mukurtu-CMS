<?php

namespace Drupal\search_api_solr\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrBackendInterface;

/**
 * Checks access for displaying Solr configuration generator actions.
 */
class LocalActionAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   (optional) The Search API server entity.
   */
  public function access(AccountInterface $account, ?ServerInterface $search_api_server = NULL) {
    if ($search_api_server && $search_api_server->getBackend() instanceof SolrBackendInterface) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
