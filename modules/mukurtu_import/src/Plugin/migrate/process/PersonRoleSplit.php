<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Splits a person field cell into names, setting their roles aside.
 *
 * A cell lists people separated by the multi-value delimiter, each
 * optionally followed by ">" and a role, matching Mukurtu's "Community>Role"
 * convention for user memberships:
 *
 * @code
 * Eunice Kitto>Singer;Alice Fletcher;Mary Jones>
 * @endcode
 *
 * Each entry splits on its last ">", so a name may contain ">" but a role
 * cannot. Returns just the names, for the usual lookup and auto-create
 * steps. The roles are stored on the row under "<destination>/_roles", one
 * entry per name with the name text as written ("token") and the role:
 * NULL when the entry has no ">" (keep the person's current role), '' for
 * "Name>" (remove it), or the role text. ProtocolAwareEntityContent applies
 * them by matching each saved person back to their token, so a name that
 * fails to import can't shift anyone else's role.
 *
 * Available configuration keys:
 * - delimiter: The multi-value delimiter. Defaults to ";".
 */
#[MigrateProcess(
  id: 'mukurtu_person_role_split',
  handle_multiples: TRUE,
)]
class PersonRoleSplit extends ProcessPluginBase {

  /**
   * Separates a name from its role.
   */
  const ROLE_SEPARATOR = '>';

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $delimiter = $this->configuration['delimiter'] ?? ';';
    $cells = is_array($value) ? $value : explode($delimiter, (string) $value);

    $names = [];
    $roles = [];
    foreach ($cells as $cell) {
      $entry = trim((string) $cell);
      if ($entry === '') {
        continue;
      }
      $role = NULL;
      $position = strrpos($entry, self::ROLE_SEPARATOR);
      if ($position !== FALSE) {
        $role = trim(substr($entry, $position + 1));
        $entry = trim(substr($entry, 0, $position));
        if ($entry === '') {
          continue;
        }
      }
      $names[] = $entry;
      $roles[] = ['token' => $entry, 'role' => $role];
    }

    $row->setDestinationProperty($destination_property . '/_roles', $roles);
    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple(): bool {
    return TRUE;
  }

}
