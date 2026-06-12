<?php

namespace Drupal\mukurtu_media\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Publishes selected media items.
 *
 * @Action(
 *   id = "mukurtu_media_publish_action",
 *   label = @Translation("Publish"),
 *   type = "media",
 * )
 */
class PublishMediaAction extends ViewsBulkOperationsActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object) {
      $object->setPublished()->save();
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
