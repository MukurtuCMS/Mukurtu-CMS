<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Extracts a substring using preg_match().
 *
 * Example to match first set of curly braces:
 * @code
 * process:
 *   guid:
 *     plugin: preg_match
 *     source: source
 *     pattern: '/\{([^}]*)\}/'
 *     group_index: 1
 * @endcode
 *
 * Example to extract the url attribute value:
 * @code
 * process:
 *   guid:
 *     plugin: preg_match
 *     source: source
 *     pattern: '/url="([^"]+)"/'
 *     group_index: 1
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "preg_match"
 * )
 */
#[MigrateProcess(id: 'preg_match')]
class PregMatch extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $pattern = $this->configuration['pattern'] ?? NULL;
    $group_index = $this->configuration['group_index'] ?? 0;

    if (!$pattern) {
      throw new \InvalidArgumentException("preg_match process plugin requires a 'pattern' configuration.");
    }

    if (!is_scalar($value)) {
      return NULL;
    }

    if (preg_match($pattern, (string) $value, $matches)) {
      return $matches[$group_index] ?? NULL;
    }

    return NULL;
  }

}
