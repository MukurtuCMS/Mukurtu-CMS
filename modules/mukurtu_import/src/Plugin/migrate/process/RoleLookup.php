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
 * site-wide administrative access. Roles carrying any permission Drupal
 * marks 'restrict access' (the same flag core's permissions UI uses to warn
 * "trusted roles only") are rejected too, so a differently-named custom role
 * with equivalent dangerous permissions can't sidestep that protection.
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
    if (is_string($result) && $this->roleGrantsRestrictedPermission($result)) {
      throw new MigrateException(sprintf('The %s role cannot be assigned via import because it grants a restricted-access permission.', $result));
    }
    return $result;
  }

  /**
   * Determines if a role grants any permission marked 'restrict access'.
   *
   * @param string $role_id
   *   The role machine name.
   *
   * @return bool
   *   TRUE if the role has at least one restricted-access permission.
   */
  protected function roleGrantsRestrictedPermission(string $role_id): bool {
    $role = \Drupal::entityTypeManager()->getStorage('user_role')->load($role_id);
    if (!$role) {
      return FALSE;
    }
    $definitions = \Drupal::service('user.permissions')->getPermissions();
    foreach ($role->getPermissions() as $permission) {
      if (!empty($definitions[$permission]['restrict access'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
