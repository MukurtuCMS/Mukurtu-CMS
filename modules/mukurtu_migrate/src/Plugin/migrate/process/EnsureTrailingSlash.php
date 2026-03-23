<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Ensures a URL value has a trailing slash.
 */
#[MigrateProcess(id: 'ensure_trailing_slash', handle_multiples: FALSE)]
class EnsureTrailingSlash extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_string($value) && $value !== '' && !str_ends_with($value, '/')) {
      return $value . '/';
    }
    return $value;
  }

}
