<?php

namespace Drupal\mukurtu_submissions;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for the mukurtu_submission entity.
 *
 * Deliberately independent of the referenced content entity's own access -
 * viewing/updating submitter contact details is gated purely on the
 * "review mukurtu submissions" permission, never on node ownership.
 */
class SubmissionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'review mukurtu submissions');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Created programmatically by PublicSubmissionForm, never through the
    // entity add UI.
    return AccessResult::forbidden();
  }

}
