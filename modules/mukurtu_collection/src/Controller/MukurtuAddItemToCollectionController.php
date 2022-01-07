<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

class MukurtuAddItemToCollectionController extends ControllerBase {
  /**
   * Check access for editing the collection via the edit_index route.
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
    if ($this->isValidCollectionItemBundle($node)) {
      //if ($this->userCanCreateCollections() || $this->userCanEditExistingCollections($node)) {
      if ($this->userCanEditExistingCollections($node)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  protected function isValidCollectionItemBundle(NodeInterface $node) {
    // Check if the node is of a bundle that can be added to a collection.
    $config = $this->entityTypeManager()->getStorage('field_config')->load('node.collection.' . MUKURTU_COLLECTION_FIELD_NAME_ITEMS);
    if ($config) {
      $settings = $config->getSettings();
      if (isset($settings['handler_settings']['target_bundles'])) {
        $bundles = $settings['handler_settings']['target_bundles'];
        if (in_array($node->bundle(), $bundles)) {
          // The node is of the correct bundle.
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Check if the current user can create or edit collections.
   */
  protected function userCanCreateCollections() {
    // If the user can create a new collection, that's good enough.
    $url = Url::fromRoute('node.add', ['node_type' => 'collection']);
    if ($url->access(NULL)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   *  Check if the user can edit any collections that don't already
   *  contain the node.
   */
  protected function userCanEditExistingCollections(NodeInterface $node) {
    $validCollections = $this->getValidCollections($node);
    if (!empty($validCollections)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return a list of collections the item can be added to.
   */
  protected function getValidCollections(NodeInterface $node) {
    // For the life of me I cannot get <> or NOT IN to work
    // correctly so we are finding all collections as well
    // as all collections that contain the item and doing
    // a diff to get the set of collections that don't
    // contain the item.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->condition(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $node->id(), '=')
      ->sort('changed', 'DESC');
    $collectionsThatContainItem = $query->execute();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->sort('changed', 'DESC');
    $allCollections = $query->execute();

    $collections = [];
    $collectionsNids = array_diff($allCollections, $collectionsThatContainItem);
    if (!empty($collectionsNids)) {
      // This might be too slow for an access check.
      // Might need to push this part to the form.
      $collections = $this->entityTypeManager()->getStorage('node')->loadMultiple($collectionsNids);
      foreach ($collections as $delta => $collection) {
        // Remove collections the user cannot update.
        if (!$collection->access('update')) {
          unset($collections[$delta]);
          continue;
        }

        // Remove the collection itself (circular reference).
        if ($collection->id() == $node->id()) {
          unset($collections[$delta]);
        }
      }
    }

    return $collections;
  }

  /**
   * Add item to collection page.
   */
  public function content(NodeInterface $node) {
    $build = [];

    // Existing collection.
    $collections = $this->getValidCollections($node);
    if (!empty($collections)) {
      $build['existing'] = $this->formBuilder()->getForm('Drupal\mukurtu_collection\Form\MukurtuAddItemToCollectionForm', $node, $collections);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(NodeInterface $node) {
    return $this->t("Add %node to Collection", ['%node' => $node->getTitle()]);
  }

}
