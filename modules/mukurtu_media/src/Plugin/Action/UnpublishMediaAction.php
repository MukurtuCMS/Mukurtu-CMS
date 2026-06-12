<?php

namespace Drupal\mukurtu_media\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Unpublishes selected media items.
 *
 * @Action(
 *   id = "mukurtu_media_unpublish_action",
 *   label = @Translation("Unpublish"),
 *   type = "media",
 * )
 */
class UnpublishMediaAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object) {
      $object->setUnpublished()->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object && $object->access('update', $account)
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
