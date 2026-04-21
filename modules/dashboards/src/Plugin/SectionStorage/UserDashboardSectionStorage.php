<?php

namespace Drupal\dashboards\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\dashboards\Entity\Dashboard;
use Drupal\layout_builder\TempStoreIdentifierInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class DashboardSectionStorage.
 *
 * @SectionStorage(
 *   id = "dashboards_override",
 *   weight = 20,
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:dashboard")
 *   },
 *   handles_permission_check = TRUE,
 * )
 *
 * @package Drupal\dashboards\Plugin\SectionStorage
 */
class UserDashboardSectionStorage extends DashboardSectionStorage implements TempStoreIdentifierInterface {

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {
    $requirements = [];
    $this->buildLayoutRoutes(
      $collection,
      $this->getPluginDefinition(),
      'dashboard/{dashboard}/override',
      [
        'parameters' => [
          'dashboard' => [
            'type' => 'entity:dashboard',
          ],
        ],
        'view_mode' => 'user',
      ],
      $requirements,
      ['_admin_route' => FALSE],
      '',
      'dashboard'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    $id = !empty($value) ? $value : (!empty($defaults['dashboard']) ? $defaults['dashboard'] : NULL);
    if ($id && ($entity = $this->entityTypeManager->getStorage('dashboard')->load($id))) {
      /**
       * @var \Drupal\dashboards\Entity\Dashboard $entity
       */
      if ($entity->isOverridden()) {
        $entity->loadOverrides();
      }
      $contexts[Dashboard::CONTEXT_TYPE] = EntityContext::fromEntity($entity);
    }
    return $contexts;
  }

  /**
   * Saves the sections.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save() {
    $sections = [];

    foreach ($this->getDashboard()->get('sections') as $delta => $section) {
      $sections[$delta] = $section->toArray();
    }
    $this->userData->set('dashboards', $this->account->id(), $this->getDashboard()->id(), serialize($sections));
    Cache::invalidateTags($this->getDashboard()->getCacheTags());
    return SAVED_UPDATED;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = $this->account;
    }
    $result = AccessResult::allowedIfHasPermission($account, 'administer dashboards')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'can override ' . $this->getDashboard()->id() . ' dashboard'));
    if ($return_as_object) {
      return $result;
    }
    return $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getTempstoreKey() {
    return $this->getDashboard()->id() . '_' . $this->account->id();
  }

}
