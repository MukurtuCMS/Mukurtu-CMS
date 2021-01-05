<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class MukurtuCollectionAddPageController extends ControllerBase {
  /**
   * Check access for adding new pages to multipage collections.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The node that is a member of the multipage collection or
   *   the collection itself.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    if ($node) {
      // Node is the collection.
      if ($node->bundle() == 'collection') {
        // User can add pages if they can edit the collection.
        return AccessResult::allowedIf($node->access('update', $account));
      }

      // Node might be a member of the collection.
      if ($node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
        // Get the multipage collection.
        $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
        $collection = $collections[0] ?? NULL;

        // The collection must exist and it must be in multipage mode.
        if ($collection && $collection->get(MUKURTU_COLLECTION_FIELD_NAME_COLLECTION_TYPE)->value == 'multipage') {

          // Check if user has edit rights to the collection.
          return AccessResult::allowedIf($node->access('update', $account));
        }
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * Display the edit form for the multipage collection.
   */
  public function content(NodeInterface $node) {
    if ($node->bundle() == 'collection') {
      $collection = $node;
    } else {
      if ($node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
        // Get the multipage collection that node belongs to.
        $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
        $collection = $collections[0] ?? NULL;
      }
    }

    if ($collection) {
      // Get the allowed target bundles from the collection items field.
      $items_field = $collection->getFieldDefinition(MUKURTU_COLLECTION_FIELD_NAME_ITEMS);
      $settings = $items_field->getSettings();
      $target_bundles = $settings['handler_settings']['target_bundles'];

      // Get the bundle labels.
      $bundle_labels = node_type_get_names();

      // If available, remove collection as a page option.
      if (isset($target_bundles['collection'])) {
        unset($target_bundles['collection']);
      }

      // Create a list of links.
      $markup = "<h2>" . $this->t("Select the type of new page to add to %collection", ['%collection' => $collection->getTitle()]) . "<h2>";
      $markup .= "<ul>";
      foreach ($target_bundles as $bundle) {
        $markup .= "<li><a href='/node/{$node->id()}/multipage/add-page/{$bundle}'>{$bundle_labels[$bundle]}</a></li>";
      }
      $markup .= "</ul>";
      $build = [
        '#markup' => $markup,
      ];
      return $build;
    }
  }

}
