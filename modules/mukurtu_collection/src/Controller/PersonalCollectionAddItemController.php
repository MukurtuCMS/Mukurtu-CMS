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
   * Check if the user owns any personal collection eligible for this item.
   *
   * Uses the same selection handler/settings PersonalCollectionAddItemForm's
   * Tagify picker uses, so this check and what the widget actually offers
   * never disagree.
   */
  protected function hasEligiblePersonalCollections(NodeInterface $node) {
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance([
      'target_type' => 'personal_collection',
      'handler' => 'mukurtu_eligible_container',
      'mukurtu_containing_field' => 'field_items_in_collection',
      'mukurtu_current_item' => $node->id(),
      'mukurtu_owned_by_current_user' => TRUE,
    ]);

    return !empty($handler->getReferenceableEntities(NULL, 'CONTAINS', 1));
  }

  /**
   * Add item to personal collection page.
   */
  public function content(NodeInterface $node) {
    $build = [];

    // Existing collection. Only show the picker if there's actually a
    // personal collection eligible to add to - it's a required field, so
    // with no eligible collections it would just be a dead end.
    $hasExistingCollections = $this->hasEligiblePersonalCollections($node);
    if ($hasExistingCollections) {
      $build['existing'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_collection\Form\PersonalCollectionAddItemForm', $node);
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
      // Open by default when there's no existing collection to pick from,
      // since it's then the only actionable option in the dialog.
      '#open' => !$hasExistingCollections,
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
