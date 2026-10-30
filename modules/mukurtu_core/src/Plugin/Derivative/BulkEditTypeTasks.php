<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One tab per content type on the bulk edit page.
 */
class BulkEditTypeTasks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected readonly BulkEditColumns $columns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('mukurtu_core.bulk_edit_columns'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $route = 'view.' . BulkEditColumns::VIEW_ID . '.page';
    $weight = 0;
    foreach ($this->columns->types() as $id => $type) {
      $this->derivatives[$id] = [
        'title' => $type->label(),
        'route_name' => $route,
        'route_parameters' => ['arg_0' => $id],
        'base_route' => $route,
        'weight' => $weight++,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
