<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\Core\Form\FormStateInterface;

class MukurtuAddNewProtocolController extends ControllerBase {

  /**
   * Check access for adding new community records.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The community node in which to create a new protocol.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    // This page deals with communities only.
    if ($node->bundle() != 'community') {
      return AccessResult::forbidden();
    }

    // User needs to be able to update the community and create new protocols
    // in that community.
    if ($node->access('update', $account)) {
      $membership = Og::getMembership($node, $account);
      return AccessResult::allowedIf($membership->hasPermission('create protocol content'));
    }

    return AccessResult::forbidden();
  }

  public function content(NodeInterface $node) {
    // Create a new node form for the given bundle.
    $protocol = Node::create([
      'type' => 'protocol',
      MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY => $node->id(),
      MUKURTU_PROTOCOL_FIELD_NAME_MEMBERSHIP_HANDLER => 'manual',
    ]);

    // Get the node form.
    $form = \Drupal::service('entity.manager')
        ->getFormObject('node', 'default')
        ->setEntity($protocol);

    $build[] = \Drupal::formBuilder()->getForm($form);
    $build[0][MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY]['#access'] = FALSE;
    return $build;
  }

}
