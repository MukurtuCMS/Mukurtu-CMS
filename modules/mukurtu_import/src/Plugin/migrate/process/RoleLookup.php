<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Looks up a user role by machine name or label for import.
 *
 * The Administrator role -- or any other role Drupal itself considers a full
 * super-admin role via RoleInterface::isAdmin(), regardless of machine name
 * -- can never be assigned via import, to prevent CSV data from being able
 * to grant site-wide administrative access. Roles carrying a permission from
 * a fixed list of genuinely dangerous, site-wide-control permissions are
 * rejected too, so a differently-named custom role with an equivalent
 * permission can't sidestep that protection.
 *
 * This is deliberately narrower than Drupal core's generic 'restrict access'
 * permission flag, which core and Mukurtu's own modules both use broadly
 * just to mean "trusted roles only" (e.g. on scoped feature-admin
 * permissions like 'administer mukurtu_import_strategy') -- that flag isn't
 * a reliable signal that a permission grants site-wide escalation.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_role_lookup"
 * )
 */
class RoleLookup extends MukurtuEntityLookup {

  /**
   * Permissions that grant genuine site-wide administrative control.
   *
   * Each of these lets its holder either escalate their own or others'
   * privileges, or bypass Mukurtu's protocol/community access model
   * entirely.
   */
  protected const DANGEROUS_PERMISSIONS = [
    'administer permissions',
    'administer users',
    'administer account settings',
    'administer site configuration',
    'administer modules',
    'administer software updates',
    'bypass node access',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $result = parent::transform($value, $migrate_executable, $row, $destination_property);
    if ($result === 'administrator') {
      throw new MigrateException('The Administrator role cannot be assigned via import.');
    }
    if (is_string($result)) {
      $role = \Drupal::entityTypeManager()->getStorage('user_role')->load($result);
      if ($role) {
        if ($role->isAdmin()) {
          throw new MigrateException('The Administrator role cannot be assigned via import.');
        }
        $dangerous_permission = $this->getDangerousPermission($role);
        if ($dangerous_permission) {
          throw new MigrateException(sprintf('The %s role cannot be assigned via import because it grants the "%s" permission.', $result, $dangerous_permission));
        }
      }
    }
    return $result;
  }

  /**
   * Returns the first permission this role holds from DANGEROUS_PERMISSIONS.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role to check.
   *
   * @return string|null
   *   The offending permission machine name, or NULL if the role holds none
   *   of them.
   */
  protected function getDangerousPermission(\Drupal\user\RoleInterface $role): ?string {
    $matches = array_intersect($role->getPermissions(), self::DANGEROUS_PERMISSIONS);
    return $matches ? reset($matches) : NULL;
  }

}
