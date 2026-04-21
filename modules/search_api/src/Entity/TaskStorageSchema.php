<?php

namespace Drupal\search_api\Entity;

@trigger_error('\Drupal\search_api\Entity\TaskStorageSchema is deprecated in search_api:8.x-1.23 and is removed from search_api:2.0.0. There is no replacement. See https://www.drupal.org/node/3259783', E_USER_DEPRECATED);

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines a storage schema for task entities.
 *
 * @deprecated in search_api:8.x-1.23 and is removed from search_api:2.0.0.
 *   There is no replacement.
 *
 * @see https://www.drupal.org/node/3259783
 */
class TaskStorageSchema extends SqlContentEntityStorageSchema {}
