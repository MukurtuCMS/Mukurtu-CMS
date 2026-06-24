<?php

namespace Drupal\mukurtu_workflows\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Grants access to the review queue for protocol stewards, language stewards,
 * and site administrators.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "review_queue_access",
 *   title = @Translation("Review queue access"),
 *   help = @Translation("Access is granted to protocol stewards, language stewards, and site administrators.")
 * )
 */
class ReviewQueueAccess extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MembershipManagerInterface $membership_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.membership_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Protocol/language stewards and admins');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if ($account->hasPermission('bypass node access') || $account->hasPermission('administer nodes')) {
      return TRUE;
    }

    foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }
      if ($membership->hasRole('protocol-protocol-protocol_steward') || $membership->hasRole('protocol-protocol-language_steward')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_user_is_logged_in', 'TRUE');
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
    return ['user', 'og_role'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
