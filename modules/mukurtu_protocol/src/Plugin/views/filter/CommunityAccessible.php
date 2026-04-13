<?php

namespace Drupal\mukurtu_protocol\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Excludes community-only communities the current user is not a member of.
 *
 * Mirrors the visibility logic in CommunitiesPageController::page().
 *
 * @ViewsFilter("community_accessible")
 */
class CommunityAccessible extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('accessible to current user');
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Find all community-only communities.
    $private_ids = \Drupal::entityQuery('community')
      ->condition('field_access_mode', 'community-only')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($private_ids)) {
      return;
    }

    $uid = \Drupal::currentUser()->id();

    // Find which of those the current user is an active member of.
    $membership_ids = \Drupal::entityQuery('og_membership')
      ->condition('entity_type', 'community')
      ->condition('entity_id', array_values($private_ids), 'IN')
      ->condition('uid', $uid)
      ->condition('state', 'active')
      ->accessCheck(FALSE)
      ->execute();

    $accessible_private_ids = [];
    if (!empty($membership_ids)) {
      $memberships = \Drupal::entityTypeManager()
        ->getStorage('og_membership')
        ->loadMultiple($membership_ids);
      foreach ($memberships as $membership) {
        $accessible_private_ids[] = $membership->get('entity_id')->value;
      }
    }

    // Exclude private communities the user cannot access.
    $excluded_ids = array_diff(array_values($private_ids), $accessible_private_ids);

    if (empty($excluded_ids)) {
      return;
    }

    $this->ensureMyTable();
    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.$this->realField",
      $excluded_ids,
      'NOT IN'
    );
  }

}
