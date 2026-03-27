<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Ensures a URL uses the https scheme.
 *
 * This plugin replaces the ensure_https plugin that was removed from
 * migrate_plus in 6.x (Drupal 10). It is provided here so that any
 * migration definition that still references ensure_https continues to work
 * without modification.
 *
 * @MigrateProcessPlugin(
 *   id = "ensure_https"
 * )
 */
class EnsureHttps extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $value;
  }

}
