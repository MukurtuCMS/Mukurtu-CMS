<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Removes duplicate values from a multi-valued pipeline value.
 *
 * Plugins that don't declare handle_multiples (static_map, for one) are run
 * once per item when the source is an array, so several distinct source
 * values can map to the same destination value. This plugin collapses those
 * duplicates and reindexes the array so field deltas stay contiguous.
 *
 * The user migrations need this because User::preSave() strips
 * 'authenticated' from the roles list by index while iterating a copy of it;
 * two 'authenticated' entries make the second removal throw "Unable to remove
 * item at non-existing index" and the whole row fails.
 *
 * Example:
 * @code
 * process:
 *   roles:
 *     -
 *       plugin: static_map
 *       source: roles
 *       map:
 *         4: mukurtu_manager
 *       default_value: authenticated
 *     -
 *       plugin: array_unique
 * @endcode
 *
 * @see \Drupal\user\Entity\User::preSave()
 */
#[MigrateProcess(
  id: 'array_unique',
  handle_multiples: TRUE,
)]
class ArrayUnique extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      return $value;
    }
    return array_values(array_unique($value));
  }

}
