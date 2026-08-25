<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_core\Entity\RoundtripEntityInterface;

/**
 * Discovers custom entity types supported by import/export.
 *
 * Core Drupal entity types (node, media, taxonomy_term, paragraph, file,
 * user) are each already known individually to mukurtu_import and
 * mukurtu_export for their own reasons and are not covered here. This
 * registry only discovers Mukurtu *custom* content entity types, so a new
 * one only needs to implement RoundtripEntityInterface to be picked up by
 * both modules, instead of requiring edits to hardcoded arrays in each.
 */
class RoundtripEntityTypeRegistry {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the custom entity type IDs supported by import/export.
   *
   * @return string[]
   *   Entity type IDs implementing RoundtripEntityInterface, sorted
   *   alphabetically for a stable, deterministic order.
   */
  public function getCustomEntityTypeIds(): array {
    $entity_type_ids = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      $class = $definition->getClass();
      if ($class && is_subclass_of($class, RoundtripEntityInterface::class)) {
        $entity_type_ids[] = $entity_type_id;
      }
    }

    sort($entity_type_ids);
    return $entity_type_ids;
  }

}
