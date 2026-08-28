<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Confirms the language_with_fallback Search API field ships in every
 * profile-owned index's config/install, not just added via an update
 * hook.
 *
 * mukurtu_multilingual_update_40009() (added in #2049) only converges
 * *existing* sites - update hooks never run on a fresh install, and this
 * profile's hook_install() implementations never called that logic
 * either, so a fresh install got none of the 6 indexes wired at all until
 * this test's own fix shipped the field directly in each owning module's
 * config/install/search_api.index.*.yml. That's the same "ship it in
 * config/install AND provide an update hook for upgraders" pattern
 * already used for other field/config additions in this codebase.
 *
 * A pure filesystem/YAML check - no Drupal bootstrap needed.
 *
 * @see mukurtu_multilingual_update_40009()
 * @group mukurtu_multilingual
 */
class SearchApiFallbackFieldShippedConfigTest extends UnitTestCase {

  /**
   * Every profile-owned index config file, relative to the profile root.
   */
  private const INDEX_FILES = [
    'modules/mukurtu_browse/config/install/search_api.index.mukurtu_default_content_index.yml',
    'modules/mukurtu_collection/config/install/search_api.index.mukurtu_collection_index.yml',
    'modules/mukurtu_dictionary/config/install/search_api.index.mukurtu_dictionary_index.yml',
    'modules/mukurtu_search/config/install/search_api.index.mukurtu_browse_auto_index.yml',
    'modules/mukurtu_solr/config/install/search_api.index.mukurtu_default_solr_index.yml',
    'modules/mukurtu_solr/config/install/search_api.index.mukurtu_dictionary_solr_index.yml',
  ];

  /**
   * Every index ships the field with the exact shape the update hook also
   * creates - a plain string field, no datasource (it's a processor-
   * derived, index-wide property, not per-datasource).
   */
  public function testEveryIndexShipsTheFallbackField(): void {
    $profileRoot = dirname(__DIR__, 5);
    $this->assertDirectoryExists($profileRoot . '/modules', 'Sanity check: resolved profile root is wrong.');

    foreach (self::INDEX_FILES as $relativePath) {
      $path = "$profileRoot/$relativePath";
      $this->assertFileExists($path);

      $index = Yaml::parseFile($path);
      $field = $index['field_settings']['language_with_fallback'] ?? NULL;
      $this->assertNotNull($field, "$relativePath does not ship the language_with_fallback field.");
      $this->assertSame('string', $field['type'], "$relativePath: wrong field type.");
      $this->assertSame('language_with_fallback', $field['property_path'], "$relativePath: wrong property_path.");
      $this->assertArrayNotHasKey('datasource_id', $field, "$relativePath: should have no datasource_id - it's an index-wide processor property.");

      // The processor itself must also actually be enabled, or the field
      // would resolve to nothing at index time.
      $this->assertArrayHasKey('language_with_fallback', $index['processor_settings'] ?? [], "$relativePath: language_with_fallback processor is not enabled.");
    }
  }

}
