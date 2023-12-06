<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\user\Plugin\migrate\source\d7\User;

/**
 * Just like Drupal 7 user source from database plugin but we exclude UID 1.
 *
 * @MigrateSource(
 *   id = "d7_non_admin_user",
 *   source_module = "user"
 * )
 */
class NonAdminUser extends User {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u')
      ->condition('u.uid', 2, '>');
  }
}
