<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_collection\Entity\Collection;

/**
 * Returns responses for Mukurtu Collections routes.
 */
class CollectionOrganizationController extends ControllerBase {

  protected function getEligibleChildCollections(AccountInterface $account) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->condition('field_parent_collection', NULL)
      ->accessCheck(TRUE);
    $results = $query->execute();

    return $results;
  }

  protected function getCollectionOrganization(Collection $collection) {
    $organization = [];
    $visited = [];
    $s = [[$collection, 0, 0, 0]];
    while(!empty($s)) {
      list($c, $parent_id, $weight, $level) = array_pop($s);
      if (!isset($visited[$c->id()])) {
        $visited[$c->id()] = TRUE;
        $organization[] = ['title' => $c->getTitle(), 'id' => $c->id(), 'collection' => $c, 'parent' => $parent_id, 'weight' => $weight++, 'level' => $level];
        $childWeight = 0;
        $children = $c->getChildCollections();
        $children = array_reverse($children);
        foreach ($children as $child) {
          $childInfo = [$child, $c->id(), $childWeight++, $level + 1];
          array_push($s, $childInfo);
        }
      }
    }
    return $organization;
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
      if ($this->hasUpdateAccessToAllChildCollections($node, $account) && count($node->getChildCollectionIds()) > 0) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  public function build(NodeInterface $node) {
    $build = [];
    $eligibleCollections = [];//$this->getEligibleChildCollections($this->currentUser());
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\mukurtu_collection\Form\CollectionOrganizationForm', $this->getCollectionOrganization($node), $eligibleCollections);
    return $build;
  }

}
