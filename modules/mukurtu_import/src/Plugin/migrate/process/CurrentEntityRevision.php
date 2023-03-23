<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\mukurtu_import\Plugin\migrate\process\MukurtuEntityLookup;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;


/**
 * This plugin takes an entity ID and lookups up the current revision.
 *
 * @MigrateProcessPlugin(
 *   id = "current_entity_revision"
 * )
 */
class CurrentEntityRevision extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $parts = explode('/', $destination_property);
    $destination_property = reset($parts);
    $entity_type = $this->configuration['entity_type'] ?? NULL;

    if (!$entity_type) {
      throw new MigrateException('You must specify an entity type.');
    }

    $target_id = $value;
    if (is_array($value)) {
      $target_id = $value['target_id'] ?? NULL;
      if (!$target_id) {
        throw new MigrateException(sprintf('"%s" is not a valid entity reference.', $value));
      }
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($target_id);
    if ($entity && $entity instanceof RevisionableInterface) {
      return [
        'target_id' => $target_id,
        'target_revision_id' => $entity->getRevisionId(),
      ];
    }
    throw new MigrateException(sprintf('Could not lookup current revision for ID "%s".', $target_id));
  }

}
