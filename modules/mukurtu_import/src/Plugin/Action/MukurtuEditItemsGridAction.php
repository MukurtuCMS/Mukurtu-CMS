<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Opens the selected items in a grid, to give each its own values.
 *
 * The action has no settings form and points VBO's confirmation step at the
 * grid, which saves each row itself.
 *
 * @see \Drupal\mukurtu_import\Form\BulkEditGridForm
 */
#[Action(
  id: 'mukurtu_edit_items_grid_action',
  label: new TranslatableMarkup('Edit items one by one'),
  confirm_form_route_name: 'mukurtu_import.bulk_edit_grid',
  type: '',
)]
class MukurtuEditItemsGridAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // The grid saves each row; VBO never runs this action itself.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account ??= \Drupal::currentUser();
    // VBO 4.4 has no action-level access check, so the "Use bulk editing"
    // permission is checked here, for each item.
    $result = $account->hasPermission('use mukurtu bulk edit')
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden()->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
