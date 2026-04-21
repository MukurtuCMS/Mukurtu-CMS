<?php

declare(strict_types=1);

namespace Drupal\migrate_tools;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Merged included shared migrate configuration.
 */
final class MigrateIncludeHandler {

  public function __construct(
    private readonly PluginManagerInterface $sharedConfiguration,
  ) {}

  /**
   * Include the shared configuration.
   */
  public function include(array &$migration): void {
    // Handle one or multiple includes.
    $includes = (array) $migration['include'];
    foreach ($includes as $include) {
      $definition = $this->sharedConfiguration->getDefinition($include);
      // Remove the shared configuration plugin metadata.
      unset($definition['id'], $definition['class'], $definition['provider']);
      $migration = NestedArray::mergeDeep($definition, $migration);
    }
  }

}
