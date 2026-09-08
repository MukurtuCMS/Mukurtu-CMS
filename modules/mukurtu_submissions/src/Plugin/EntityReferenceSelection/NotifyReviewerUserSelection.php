<?php

namespace Drupal\mukurtu_submissions\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Entity reference selection for the notify_uids "additional reviewers"
 * field - any user is eligible except Anonymous and the hidden submissions
 * service account, neither of which are real reviewers.
 *
 * @EntityReferenceSelection(
 *   id = "mukurtu_submissions_notify_reviewer",
 *   label = @Translation("Submission notification reviewers"),
 *   entity_types = {"user"},
 *   group = "mukurtu_submissions_notify_reviewer",
 *   weight = 1
 * )
 */
class NotifyReviewerUserSelection extends UserSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $query->condition('uid', 0, '<>');

    $service_account_uid = (int) \Drupal::config('mukurtu_submissions.settings')->get('service_account_uid');
    if ($service_account_uid) {
      $query->condition('uid', $service_account_uid, '<>');
    }

    return $query;
  }

}
