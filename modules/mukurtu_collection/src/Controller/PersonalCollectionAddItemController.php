<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_collection\Entity\PersonalCollection;

class PersonalCollectionAddItemController extends ControllerBase {
  /**
   * Check access for adding a specific item to a personal collection.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The item.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    return AccessResult::allowedIfHasPermission($account, 'add personal collection entities');
  }

  /**
   * Add item to personal collection page.
   */
  public function content(NodeInterface $node) {
    $build = [];

    // Existing collection.
    $build['existing'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_collection\Form\PersonalCollectionAddItemForm', $node);

    // New Personal Collection.
    $newCollection = PersonalCollection::create([
      'uid' => $this->currentUser()->id(),
    ]);
    $newCollection->add($node);

    $form = $this->entityTypeManager()->getFormObject('personal_collection', 'default')->setEntity($newCollection);

    $build['new_collection'] = [
      '#type' => 'details',
      '#title' => $this->t('Create a new personal collection'),
    ];
    $build['new_collection']['form'] = $this->formBuilder()->getForm($form);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(NodeInterface $node) {
    return $this->t("Add %node to Personal Collection", ['%node' => $node->getTitle()]);
  }

}
