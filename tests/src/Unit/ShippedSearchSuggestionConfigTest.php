<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the shipped search suggestion config against the views it serves.
 *
 * Search API Autocomplete ignores a display or field it doesn't recognize, so
 * a renamed display or index field would quietly turn suggestions off.
 */
#[Group('mukurtu')]
class ShippedSearchSuggestionConfigTest extends UnitTestCase {

  /**
   * Returns a shipped config file, parsed.
   */
  private function shipped(string $file): array {
    $path = dirname(__DIR__, 3) . '/' . $file;
    $this->assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Each search box that suggests words, by search ID and owning module.
   */
  public static function searchProvider(): \Generator {
    yield 'browse' => ['mukurtu_browse', 'mukurtu_browse'];
    yield 'digital heritage' => ['mukurtu_digital_heritage_browse', 'mukurtu_browse'];
    yield 'dictionary' => ['mukurtu_dictionary', 'mukurtu_dictionary'];
  }

  /**
   * The search covers every display of its view, on the view's index.
   */
  #[DataProvider('searchProvider')]
  public function testSearchMatchesItsView(string $id, string $module): void {
    $search = $this->shipped("modules/$module/config/install/search_api_autocomplete.search.$id.yml");
    $view = $this->shipped("modules/$module/config/install/views.view.$id.yml");

    $this->assertSame('search_api_index_' . $search['index_id'], $view['base_table']);
    $this->assertContains("views.view.$id", $search['dependencies']['config']);

    $settings = $search['search_settings']["views:$id"]['displays'];
    $this->assertFalse($settings['default'], 'Suggestions should be on for the listed displays only.');
    $displays = array_values(array_diff(array_keys($view['display']), ['default']));
    sort($displays);
    $selected = $settings['selected'];
    sort($selected);
    $this->assertSame($displays, $selected, "Every display of $id shares one search form, so all of them need suggestions.");
  }

  /**
   * Suggested words only come from fulltext fields the index has.
   */
  #[DataProvider('searchProvider')]
  public function testSuggestedFieldsExist(string $id, string $module): void {
    $search = $this->shipped("modules/$module/config/install/search_api_autocomplete.search.$id.yml");
    $index_module = $search['index_id'] === 'mukurtu_dictionary_index' ? 'mukurtu_dictionary' : 'mukurtu_browse';
    $index = $this->shipped("modules/$index_module/config/install/search_api.index.{$search['index_id']}.yml");

    foreach ($search['suggester_settings']['server']['fields'] as $field) {
      $this->assertSame('text', $index['field_settings'][$field]['type'] ?? NULL, "$id suggests from '$field', which is not a fulltext field of {$search['index_id']}.");
    }
  }

  /**
   * The index filters out items the viewer can't see.
   *
   * Word suggestions and the header's item counts rely on this processor;
   * only the header's item list checks each item again.
   */
  #[DataProvider('searchProvider')]
  public function testIndexChecksAccess(string $id, string $module): void {
    $search = $this->shipped("modules/$module/config/install/search_api_autocomplete.search.$id.yml");
    $index_module = $search['index_id'] === 'mukurtu_dictionary_index' ? 'mukurtu_dictionary' : 'mukurtu_browse';
    $index = $this->shipped("modules/$index_module/config/install/search_api.index.{$search['index_id']}.yml");

    $this->assertArrayHasKey('content_access', $index['processor_settings'], "{$search['index_id']} must check content access.");
  }

  /**
   * Suggestions are built from the view's query, so it must check access.
   *
   * With bypass_access on, Search API skips the content_access processor and
   * suggestions would include words from items the viewer can't see.
   */
  #[DataProvider('searchProvider')]
  public function testViewChecksAccess(string $id, string $module): void {
    $view = $this->shipped("modules/$module/config/install/views.view.$id.yml");
    foreach ($view['display'] as $display_id => $display) {
      $this->assertNotTrue($display['display_options']['query']['options']['bypass_access'] ?? NULL, "$id display $display_id bypasses access.");
    }
  }

  /**
   * New sites ship with both kinds of suggestions turned on.
   */
  public function testSuggestionsShipOn(): void {
    $settings = $this->shipped('modules/mukurtu_search/config/install/mukurtu_search.settings.yml');
    $this->assertTrue($settings['search_suggestions_enabled']);
    $this->assertTrue($settings['header_suggestions_enabled']);
  }

  /**
   * Dictionary words come from every field the dictionary search box matches.
   */
  public function testDictionarySuggestionSources(): void {
    $search = $this->shipped('modules/mukurtu_dictionary/config/install/search_api_autocomplete.search.mukurtu_dictionary.yml');
    $suggested = $search['suggester_settings']['server']['fields'];
    sort($suggested);

    // With no fields set, the search box matches every fulltext field.
    $view = $this->shipped('modules/mukurtu_dictionary/config/install/views.view.mukurtu_dictionary.yml');
    $this->assertEmpty($view['display']['default']['display_options']['filters']['search_api_fulltext']['fields']);
    $index = $this->shipped('modules/mukurtu_dictionary/config/install/search_api.index.mukurtu_dictionary_index.yml');
    $fulltext = array_keys(array_filter($index['field_settings'], fn ($field) => $field['type'] === 'text'));
    sort($fulltext);

    $this->assertSame($fulltext, $suggested);
  }

}
