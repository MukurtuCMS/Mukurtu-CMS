<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_search\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps every shipped database index under MySQL's 64 keys per table.
 *
 * The search_api_db module gives each index a table with a primary key, plus
 * one key per field and per special field (datasource and language), so an
 * index can hold at most 61 fields (#2088, #2522). Counting shipped fields
 * alone, as BrowseAutoIndexKeyBudgetTest does, misses those three keys and
 * the fields other modules add when they're installed.
 */
#[Group('mukurtu_search')]
class ShippedDatabaseIndexKeyBudgetTest extends UnitTestCase {

  /**
   * MySQL's limit on keys per table.
   */
  const MAX_KEYS = 64;

  /**
   * Keys search_api_db adds besides one per field.
   */
  const EXTRA_KEYS = 3;

  /**
   * Fields other modules add to shipped indexes when they're installed.
   *
   * @see mukurtu_community_records_install()
   * @see mukurtu_multipage_items_install()
   * @see mukurtu_collection_add_display_weight_to_index()
   */
  const INSTALL_TIME_FIELDS = [
    'mukurtu_default_content_index' => 2,
    'mukurtu_collection_index' => 1,
  ];

  /**
   * Every shipped database index fits, counting all of its keys.
   */
  public function testKeysFit(): void {
    $profile = dirname(__DIR__, 5);
    $files = glob("$profile/modules/*/config/install/search_api.index.*.yml");
    $checked = 0;
    foreach ($files as $file) {
      $index = Yaml::parseFile($file);
      if (($index['server'] ?? NULL) !== 'mukurtu_database_server') {
        continue;
      }
      $keys = count($index['field_settings'] ?? []) + (self::INSTALL_TIME_FIELDS[$index['id']] ?? 0) + self::EXTRA_KEYS;
      $this->assertLessThanOrEqual(self::MAX_KEYS, $keys, "{$index['id']} needs $keys keys.");
      $checked++;
    }
    $this->assertGreaterThanOrEqual(4, $checked, 'All four shipped database indexes were found.');
  }

}
