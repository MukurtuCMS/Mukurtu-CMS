<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Controller to view Community Records.
 */
class CommunityRecordsViewController extends ControllerBase {

  /**
   * Access Check for Community Records Display.
   */
  public function access(NodeInterface $node, AccountInterface $account) {
    return $node->access('view', $account, TRUE);
  }

  /**
   * Community Records Display.
   */
  public function viewRecords(NodeInterface $node) {
    $viewBuilder = $this->entityTypeManager()->getViewBuilder('node');
    $displayMode = $this->getRecordDisplayMode($node);

    foreach ($this->getAllRecords($node) as $record) {
      $records[] = [
        'id' => "record-{$record->id()}",
        'communities' => $this->getCommunitiesLabel($record),
        'title' => $record->getTitle(),
        'content' => $viewBuilder->view($record, $displayMode),
      ];
    }

    $build['template'] = [
      '#theme' => 'community_records',
      '#records' => $records,
      '#attached' => ['library' => ['field_group/element.horizontal_tabs']],
    ];

    return $build;
  }

  /**
   * Build the communities label.
   */
  protected function getCommunitiesLabel(NodeInterface $node) {
    $communities = $node->get('field_communities')->referencedEntities();

    $communityLabels = [];
    foreach ($communities as $community) {
      // Skip any communities the user can't see.
      if (!$community->access('view', $this->currentUser())) {
        continue;
      }
      // @todo ordering?
      $communityLabels[] = $community->getName();
    }
    return implode(', ', $communityLabels);
  }

  /**
   * Get the display mode for community records.
   */
  protected function getRecordDisplayMode(NodeInterface $node) {
    return 'default';
  }

  /**
   * Find all records associated with a node.
   */
  protected function getAllRecords(NodeInterface $node) {
    // Doesn't support/not configured for community records,
    // so exit early and display the single node.
    if (!$node->hasField('field_mukurtu_original_record')) {
      return [$node];
    }

    // Find the original record.
    $original_record = $node->get('field_mukurtu_original_record')->referencedEntities()[0] ?? $node;

    // Check if the user can actually see the original record.
    if ($original_record->access('view', $this->currentUser())) {
      $allRecords = [$original_record->id() => $original_record];
    }

    // Find all the community records for the original record.
    // The entity query system takes care of access checks for us here.
    $query = $this->entityTypeManager()->getStorage($node->getEntityTypeId())->getQuery()
      ->condition('field_mukurtu_original_record', $original_record->id())
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');
    $results = $query->execute();

    $records = $this->entityTypeManager()->getStorage($node->getEntityTypeId())->loadMultiple($results);
    $allRecords = $allRecords + $records;

    // @todo Ordering.

    return $allRecords;
  }

}
