<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class MukurtuCollectionEditIndexController extends ControllerBase {
  /**
   * Check access for editing the collection via the edit_index route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The node that is a member of the multipage collection.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    if ($node && $node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
      // Get the multipage collection.
      $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
      $collection = $collections[0] ?? NULL;

      // The collection must exist and it must be in multipage mode.
      if ($collection && $collection->get(MUKURTU_COLLECTION_FIELD_NAME_COLLECTION_TYPE)->value == 'multipage') {

        // Check if user has edit rights to the collection.
        return AccessResult::allowedIf($node->access('update', $account));
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * Display the edit form for the multipage collection.
   */
  public function content(NodeInterface $node) {
    if ($node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
      // Get the multipage collection that node belongs to.
      $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
      $collection = $collections[0] ?? NULL;

      // Display the edit form for that collection.
      $form = \Drupal::service('entity.manager')
        ->getFormObject('node', 'default')
        ->setEntity($collection);

      $build[] = \Drupal::formBuilder()->getForm($form);
      return $build;
    }
  }

}
