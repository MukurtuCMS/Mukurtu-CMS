<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;
/**
 * Controller to view Community Records.
 */
class CommunityRecordsViewController extends NodeViewController {

  /**
   * Community Records Display.
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    // If this display mode isn't set to display community records,
    // fall back to the default node view controller.
    if (!$this->isRecordDisplayMode($node, $view_mode)) {
      return parent::view($node, $view_mode, $langcode);
    }

    // If CRs are broken or there are no CRs, fall back to the default node
    // view controller.
    $allRecords = $this->getAllRecords($node);
    if (empty($allRecords) || count($allRecords) == 1) {
      return parent::view($node, $view_mode, $langcode);
    }

    foreach ($allRecords as $record) {
      $records[] = [
        'id' => $record->id(),
        'tabid' => "record-{$record->id()}",
        'communities' => $this->getCommunitiesLabel($record),
        'title' => $record->getTitle(),
        'content' => parent::view($record, $view_mode),
      ];
    }

    $build['template'] = [
      '#theme' => 'community_records',
      '#active' => $node->id(),
      '#records' => $records,
      '#attached' => ['library' => ['field_group/element.horizontal_tabs']],
    ];

    return $build;
  }

  /**
   * Build the communities label.
   */
  protected function getCommunitiesLabel(EntityInterface $node) {
    $communities = $node->get('field_communities')->referencedEntities();

    $communityLabels = [];
    foreach ($communities as $community) {
      // Skip any communities the user can't see.
      if (!$community->access('view', $this->currentUser)) {
        continue;
      }
      // @todo ordering?
      $communityLabels[] = $community->getName();
    }
    return implode(', ', $communityLabels);
  }

  /**
   * Check if a display mode is configured for community records.
   *
   * @param EntityInterface $node
   *   The node being displayed.
   * @param string $view_mode
   *   The requested view mode.
   *
   * @return boolean
   *   TRUE if configured to display community records.
   */
  protected function isRecordDisplayMode(EntityInterface $node, $view_mode) {
    return $view_mode === 'full';
  }

  /**
   * Find all records associated with a node.
   */
  protected function getAllRecords(EntityInterface $node) {
    // Doesn't support/not configured for community records,
    // so exit early and display the single node.
    if (!$node->hasField('field_mukurtu_original_record')) {
      return [$node];
    }

    // Find the original record.
    $original_record = $node->get('field_mukurtu_original_record')->referencedEntities()[0] ?? $node;

    // Check if the user can actually see the original record.
    if ($original_record->access('view', $this->currentUser)) {
      $allRecords = [$original_record->id() => $original_record];
    }

    // Find all the community records for the original record.
    // The entity query system takes care of access checks for us here.
    $query = $this->entityTypeManager->getStorage($node->getEntityTypeId())->getQuery()
      ->condition('field_mukurtu_original_record', $original_record->id())
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');
    $results = $query->execute();

    $records = $this->entityTypeManager->getStorage($node->getEntityTypeId())->loadMultiple($results);
    $allRecords = $allRecords + $records;

    // @todo Ordering.

    return $allRecords;
  }

}
