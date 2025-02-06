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
   * Return a list of collections the item can be added to.
   */
  protected function getValidCollections(NodeInterface $node) {
    $query = \Drupal::entityQuery('personal_collection')
      ->condition('field_items_in_collection', $node->id(), '=')
      ->condition('user_id', $this->currentUser()->id(), '=')
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');
    $collectionsThatContainItem = $query->execute();

    $query = \Drupal::entityQuery('personal_collection')
      ->condition('user_id', $this->currentUser()->id(), '=')
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC');
    $allCollections = $query->execute();

    $collections = [];
    $collectionsNids = array_diff($allCollections, $collectionsThatContainItem);
    if (!empty($collectionsNids)) {
      // This might be too slow for an access check.
      // Might need to push this part to the form.
      $collections = $this->entityTypeManager()->getStorage('personal_collection')->loadMultiple($collectionsNids);
      foreach ($collections as $delta => $collection) {
        // Remove collections the user cannot update.
        if (!$collection->access('update')) {
          unset($collections[$delta]);
          continue;
        }
      }
    }

    return $collections;
  }

  /**
   * Add item to personal collection page.
   */
  public function content(NodeInterface $node) {
    $build = [];

    // Existing collection.
    $collections = $this->getValidCollections($node);
    if (!empty($collections)) {
      $build['existing'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_collection\Form\PersonalCollectionAddItemForm', $node, $collections);
    }

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
