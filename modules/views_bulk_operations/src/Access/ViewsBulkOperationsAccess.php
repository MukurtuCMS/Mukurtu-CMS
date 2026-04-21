<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\views\Views;

/**
 * Defines module access rules.
 */
class ViewsBulkOperationsAccess implements AccessInterface {

  /**
   * Object constructor.
   */
  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $routeMatch
   *   The matched route.
   */
  public function access(AccountInterface $account, RouteMatch $routeMatch): AccessResult {
    $parameters = $routeMatch->getParameters()->all();

    $view = Views::getView($parameters['view_id']);
    if ($view !== NULL) {
      // Set view arguments, sometimes needed for access checks.
      $tempstore = $this->tempStoreFactory->get('views_bulk_operations_' . $parameters['view_id'] . '_' . $parameters['display_id']);
      $view_data = $tempstore->get((string) $account->id());
      if ($view_data !== NULL) {
        $view->setArguments($view_data['arguments']);
      }
      if ($view->access($parameters['display_id'], $account)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}
