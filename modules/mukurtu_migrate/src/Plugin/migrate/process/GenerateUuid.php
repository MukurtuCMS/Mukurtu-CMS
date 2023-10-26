<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Generate a uuid.
 *
 * @MigrateProcessPlugin(
 *   id = "generate_uuid",
 *   handle_multiples = FALSE
 * )
 */
class GenerateUuid extends ProcessPluginBase
{
  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    $uuid_service = \Drupal::service('uuid');
    return $uuid_service->generate();
  }
}
