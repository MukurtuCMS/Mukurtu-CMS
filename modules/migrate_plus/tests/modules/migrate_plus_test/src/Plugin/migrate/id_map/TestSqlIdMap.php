<?php

declare(strict_types=1);

namespace Drupal\migrate_plus_test\Plugin\migrate\id_map;

use Drupal\Component\Plugin\Attribute\PluginID;
use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Defines a SQL ID map for use in tests.
 */
#[PluginID(id: 'migrate_plus_test_sql')]
class TestSqlIdMap extends Sql {

}
