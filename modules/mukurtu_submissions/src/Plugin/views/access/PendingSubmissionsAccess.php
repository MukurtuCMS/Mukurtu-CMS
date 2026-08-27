<?php

namespace Drupal\mukurtu_submissions\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Grants access to the Pending Submissions queue.
 *
 * Deliberately narrower than the existing /review-queue's access plugin
 * (which also admits OG protocol/language stewards): a fresh submission has
 * no protocol assigned yet, so this queue is reviewer/site-admin only.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "mukurtu_pending_submissions_access",
 *   title = @Translation("Pending submissions access"),
 *   help = @Translation("Access is granted to users with the 'review mukurtu submissions' permission.")
 * )
 */
class PendingSubmissionsAccess extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Submission reviewers');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('review mukurtu submissions');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_permission', 'review mukurtu submissions');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
