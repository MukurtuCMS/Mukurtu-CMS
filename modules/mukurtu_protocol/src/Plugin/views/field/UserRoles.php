<?php

namespace Drupal\mukurtu_protocol\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\user\Entity\Role;

/**
 * Provides a Site Roles field handler that renders roles as plain text.
 *
 * @ViewsField("user_roles_plain")
 */
class UserRoles extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $values->_entity;
    if (!$user) {
      return '';
    }

    $labels = [];
    // getRoles() excludes anonymous; we also skip 'authenticated' since it
    // is held by every user and adds no useful information to the list.
    foreach ($user->getRoles() as $rid) {
      if ($rid === 'authenticated') {
        continue;
      }
      $role = Role::load($rid);
      $labels[] = $role ? $role->label() : $rid;
    }
    sort($labels);

    return empty($labels) ? '' : ['#markup' => implode(', ', array_map('htmlspecialchars', $labels))];
  }

  public function query() {
  }

}
