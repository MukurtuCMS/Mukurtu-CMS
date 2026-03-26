<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Ensures a URL value uses https.
 */
#[MigrateProcess(
  id: 'ensure_https',
  handle_multiples: FALSE,
)]
class EnsureHttps extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value)) {
      return $value;
    }
    return preg_replace('~^http://~', 'https://', $value);
  }

}
