<?php

namespace Drupal\dashboards;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard permissions.
 */
class DashboardsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a DashboardsPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Gets dashboard permission.
   *
   * @return array
   *   An array of permissions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function permissions(): array {
    $permissions = [];

    foreach ($this->entityManager->getStorage('dashboard')->loadMultiple() as $dashboard) {
      $permissions += [
        'can view ' . $dashboard->id() . ' dashboard' => [
          'title' => $this->t('Can view %dashboard dashboard.', ['%dashboard' => $dashboard->label()]),
          'dependencies' => [
            $dashboard->getConfigDependencyKey() => [$dashboard->getConfigDependencyName()],
          ],
        ],
      ];

      $permissions += [
        'can override ' . $dashboard->id() . ' dashboard' => [
          'title' => $this->t('Can override %dashboard dashboard', ['%dashboard' => $dashboard->label()]),
          'dependencies' => [
            $dashboard->getConfigDependencyKey() => [$dashboard->getConfigDependencyName()],
          ],
        ],
      ];

    }

    return $permissions;
  }

}
