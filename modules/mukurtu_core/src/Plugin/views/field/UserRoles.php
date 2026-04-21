<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a Views field displaying a user's site roles as plain text.
 *
 * @ViewsField("mukurtu_user_roles")
 */
class UserRoles extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!$values->_entity instanceof UserInterface) {
      return '';
    }
    $roles = Role::loadMultiple($values->_entity->getRoles());
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    $labels = array_map(fn(RoleInterface $role) => $role->label(), $roles);
    asort($labels);
    return ['#markup' => implode(', ', $labels)];
  }

  public function query() {}

}
