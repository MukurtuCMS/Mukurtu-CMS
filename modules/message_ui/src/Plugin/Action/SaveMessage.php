<?php

namespace Drupal\message_ui\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an action that can save any message.
 *
 * @Action(
 *   id = "message_save_action",
 *   label = @Translation("Save message"),
 *   type = "message"
 * )
 */
class SaveMessage extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // We need to change at least one value, otherwise the changed timestamp
    // will not be updated.
    $entity->changed = 0;
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
