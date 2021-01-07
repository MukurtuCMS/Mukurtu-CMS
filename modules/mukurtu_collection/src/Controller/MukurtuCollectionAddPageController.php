<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class MukurtuCollectionAddPageController extends ControllerBase {

  protected function getCollection(NodeInterface $node) {
    if ($node->bundle() == 'collection') {
      return $node;
    } else {
      if ($node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
        // Get the multipage collection that node belongs to.
        $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
        $collection = $collections[0] ?? NULL;

        return $collection;
      }
    }

    return NULL;
  }

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
  public function access(AccountInterface $account, NodeInterface $node, $bundle = '') {
    if ($node) {
      // Node is the collection.
      if ($node->bundle() == 'collection') {
        // User can add pages if they can edit the collection.
        return AccessResult::allowedIf($node->access('update', $account));
      }

      // The collection must exist and it must be in multipage mode.
      $collection = $this->getCollection($node);
      if ($collection && $collection->get(MUKURTU_COLLECTION_FIELD_NAME_COLLECTION_TYPE)->value == 'multipage') {

        // Check if user has edit rights to the collection.
        return AccessResult::allowedIf($node->access('update', $account));
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * Display the list of available new content for a multipage collection.
   */
  public function content(NodeInterface $node) {
    $collection = $this->getCollection($node);

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

  /**
   * Display the creation form for the specified node bundle.
   */
  public function contentByBundle(NodeInterface $node, $bundle) {
    $collection = $this->getCollection($node);

    if (!$collection) {
      return;
    }

    // Create a new node form for the given bundle.
    $node = Node::create([
      'type' => $bundle,
      MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET => [$collection->id()],
    ]);

    // Get the node form.
    $form = \Drupal::service('entity.manager')
        ->getFormObject('node', 'default')
        ->setEntity($node);

    // Pass a custom submit handler and the collection ID to
    // mukurtu_collection_form_alter. There might be a cleaner way to do this...
    $args = [
      'mukurtu_collection' => [
        'submit' => ['mukurtu_collection_multipage_add_page_form_submit'],
        'target' => $collection->id(),
      ],
    ];
    $build[] = \Drupal::formBuilder()->getForm($form, $args);

    return $build;
  }

}
