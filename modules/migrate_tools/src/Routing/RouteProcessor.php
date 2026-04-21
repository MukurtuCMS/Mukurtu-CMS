<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface;
use Symfony\Component\Routing\Route;

/**
 * Route processor to expand migrate_group.
 */
class RouteProcessor implements OutboundRouteProcessorInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, ?BubbleableMetadata $bubbleable_metadata = NULL): void {
    if ($route->hasDefault('_migrate_group')) {
      $parameters['migration_group'] = 'default';
      if ($this->entityTypeManager->hasHandler('migration', 'storage')) {
        /** @var \Drupal\migrate_plus\Entity\MigrationInterface */
        $migration = $this->entityTypeManager
          ->getStorage('migration')
          ->load($parameters['migration']);
        if (($migration !== NULL) && $group = $migration->get('migration_group')) {
          $parameters['migration_group'] = $group;
        }
      }
    }
  }

}
