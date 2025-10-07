<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;

class CollectionAddSubcollectionController extends ControllerBase {

  /**
   * Check access for creating new sub-collections via the entity form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The parent collection.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    if ($node->getType() == 'collection') {
      return AccessResult::allowedIf($node->access('update', $account));
    }
    return AccessResult::forbidden();
  }

  public function newSubcollection(NodeInterface $node) {
     // Create a new node form for the given bundle.
    $subcollection = Node::create([
      'type' => 'collection',
      '_parent_collection' => $node->id(),
    ]);

    // Get the node form.
    $form = $this->entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($subcollection);

    $form_title = $this->t('Creating a new sub-collection in %collection', ['%collection' => $node->getTitle()]);
    $build[] = ['#type' => 'markup', '#markup' => "<h2>$form_title</h2>"];
    $build[] = $this->formBuilder()->getForm($form);

    return $build;
  }

}
