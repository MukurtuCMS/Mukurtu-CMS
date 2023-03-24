<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\EntityLookup;

/**
 * This plugin converts UUIDs to IDs.
 *
 * @MigrateProcessPlugin(
 *   id = "uuid_lookup"
 * )
 */
class UuidLookup extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($entityType = $this->configuration['entity_type'] ?? NULL) {
      if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[8|9|aA|bB][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) {
        $entities = \Drupal::entityTypeManager()->getStorage($entityType)->loadByProperties(['uuid' => $value]);
        if (!empty($entities)) {
          $entity = reset($entities);
          return $entity->id();
        }
      }
    }

    return $value;
  }
}
