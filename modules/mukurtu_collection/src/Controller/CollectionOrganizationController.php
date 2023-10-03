<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Mukurtu Collections routes.
 */
class CollectionOrganizationController extends ControllerBase {

  /**
   * Gets the IDs of collections that the given user can use as subcollections.
   *
   * Currently this is
   */
  protected function getEligibleChildCollections(AccountInterface $account) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->condition('field_parent_collection', NULL)
      ->accessCheck(TRUE);
    $results = $query->execute();

    return $results;
  }

  /**
   * Check if a user has update a access to an entire collection tree.
   *
   * This is super expensive...
   */
  protected function hasUpdateAccessToAllChildCollections(Collection $collection, AccountInterface $account) : bool {
    $childCollections = $collection->getChildCollections();
    foreach ($childCollections as $childCollection) {
      if (!$childCollection->access('update', $account)) {
        return FALSE;
      }
      return $this->hasUpdateAccessToAllChildCollections($childCollection, $account);
    }
    return TRUE;
  }

  public function access(NodeInterface $node, AccountInterface $account) {
    if ($node instanceof Collection && $node->access('update', $account)) {
      if ($this->hasUpdateAccessToAllChildCollections($node, $account)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }


  /**
   * Autocomplete handler for collection organization form.
   *
   * Finds published collections user has edit access to.
   * @see \Drupal\mukurtu_collection\Form\CollectionOrganizationForm
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (strlen($input) > 0) {
      $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('title', '%' . $input . '%', 'LIKE')
        ->condition('type', 'collection')
        ->accessCheck(TRUE)
        ->range(0, 20);
      $nids = $query->execute();

      if (!empty($nids)) {
        $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);

        // This is slightly silly. Because we're doing some individual entity
        // checks outside of the entityQuery, we're requesting 20 results from
        // the query and returning 10. The hope being at least some of the query
        // results make it through the individual checks. We don't want an empty
        // autocomplete widget. The critical field, field_parent_collection, is
        // a computed field so we can't use entityQuery on it here.
        $count = 0;
        foreach ($nodes as $node) {
          if ($node->access('update') && is_null($node->getParentCollectionId())) {
            $results[] = [
              'value' => $node->getTitle() . " ({$node->id()})",
              'label' => $node->getTitle(),
            ];
            $count += 1;
            if ($count >= 10) {
              break;
            }
          }
        }
      }
    }

    return new JsonResponse($results);
  }

  public function build(NodeInterface $node) {
    $build = [];
    $eligibleCollections = [];//$this->getEligibleChildCollections($this->currentUser());
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_collection\Form\CollectionOrganizationForm', $node, $eligibleCollections);
    return $build;
  }

}
