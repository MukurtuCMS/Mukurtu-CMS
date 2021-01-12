<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class MukurtuCollectionCreateMultipageController extends ControllerBase {
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
    if ($node && $node->bundle() != 'collection' && $node->hasField(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)) {
      // Omit basic Mukurtu types.
      if (in_array($node->bundle(), ['community', 'protocol'])) {
        return AccessResult::forbidden();
      }

      // Check if this node already is already part of a multipage collection.
      $collections = $node->get(MUKURTU_COLLECTION_FIELD_NAME_SEQUENCE_COLLECTION)->referencedEntities();
      $collection = $collections[0] ?? NULL;

      // A node can only be a member of a single multipage collection.
      if (is_null($collection)) {
        return AccessResult::allowedIfHasPermission($account, 'create collection content');
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Display the edit form for the multipage collection.
   */
  public function content(NodeInterface $node) {
    // Create a new collection entity.
    $collection = Node::create([
      'type' => 'collection',
      MUKURTU_COLLECTION_FIELD_NAME_ITEMS => [$node->id()],
      MUKURTU_COLLECTION_FIELD_NAME_COLLECTION_TYPE => MUKURTU_COLLECTION_TYPE_MULTIPAGE,
      MUKURTU_PROTOCOL_FIELD_NAME_READ => mukurtu_core_flatten_entity_ref_field($node, MUKURTU_PROTOCOL_FIELD_NAME_READ),
      MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE => $node->get(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE)->value,
      MUKURTU_PROTOCOL_FIELD_NAME_WRITE => mukurtu_core_flatten_entity_ref_field($node, MUKURTU_PROTOCOL_FIELD_NAME_WRITE),
      MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE => $node->get(MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE)->value,
    ]);

    // Display the create collection form.
    $form = \Drupal::service('entity.manager')
      ->getFormObject('node', 'default')
      ->setEntity($collection);

    // Pass a custom submit handler and the node ID to
    // mukurtu_collection_form_alter.
    $args = [
      'mukurtu_collection' => [
        'submit' => ['mukurtu_collection_multipage_create_multipage_form_submit'],
        'target' => $node->id(),
      ],
    ];

    $build[] = \Drupal::formBuilder()->getForm($form, $args);
    return $build;
  }
}
