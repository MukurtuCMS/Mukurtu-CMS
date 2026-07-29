<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Looks up a user role by machine name or label for import.
 *
 * The Administrator role can never be assigned via import, regardless of who
 * is running the import, to prevent CSV data from being able to grant
 * site-wide administrative access.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_role_lookup"
 * )
 */
class RoleLookup extends MukurtuEntityLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $result = parent::transform($value, $migrate_executable, $row, $destination_property);
    if ($result === 'administrator') {
      throw new MigrateException('The Administrator role cannot be assigned via import.');
    }
    return $result;
  }

}
