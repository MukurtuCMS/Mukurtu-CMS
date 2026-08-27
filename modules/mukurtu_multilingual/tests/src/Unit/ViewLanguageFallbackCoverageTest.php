<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards against a View shipping without the language-fallback policy.
 *
 * docs/content-language-policy.md requires visitor-facing Views to fall
 * back to default-language content instead of silently hiding it when no
 * translation exists, via one of two mechanisms depending on the view's
 * backend (a `language_with_fallback`-derived Search API field, or a
 * `default_langcode = 1` filter plus `rendering_language:
 * ***LANGUAGE_language_content***` for plain-entity views). That policy
 * was previously enforced only by a PR template checkbox - easy to miss
 * for a developer unfamiliar with the multilingual requirements. This test
 * globs every shipped `views.view.*.yml` and fails CI if a view implements
 * neither mechanism and has no entry in EXEMPTIONS explaining why it's out
 * of scope.
 *
 * A pure filesystem/YAML check - no Drupal bootstrap needed.
 *
 * @group mukurtu_multilingual
 */
class ViewLanguageFallbackCoverageTest extends UnitTestCase {

  /**
   * Views that intentionally don't implement the fallback policy.
   *
   * Keyed by view machine name (the `id` in its views.view.*.yml), value is
   * the reason it's exempt. See docs/content-language-policy.md.
   */
  private const EXEMPTIONS = [
    // Admin-only listings (not visitor-facing browse/search).
    'mukurtu_manage_all_content' => 'admin/content - editorial content management, not a visitor-facing listing',
    'mukurtu_all_paragraphs' => 'admin/paragraphs-list - admin tooling',
    'mukurtu_import_results_communities' => 'admin import-results review UI',
    'mukurtu_import_results_content' => 'admin import-results review UI',
    'mukurtu_import_results_cultural_protocols' => 'admin import-results review UI',
    'mukurtu_import_results_media' => 'admin import-results review UI',
    'mukurtu_import_results_multipage_items' => 'admin import-results review UI',
    'mukurtu_import_results_taxonomy_terms' => 'admin import-results review UI',
    'mukurtu_comments' => 'admin/comment, admin/unapproved-comments - comments are not independently translatable content',
    'mukurtu_people' => 'admin/people/list - core People-view boilerplate on the user entity, not content',
    'user_admin_people' => 'admin/people/list - core People-view boilerplate on the user entity, not content',
    'mukurtu_pending_submissions' => 'admin/content/pending-submissions - admin review queue',
    'mukurtu_media' => 'admin/content/media - admin media listing (ships from config/optional)',

    // Not translatable content at all.
    'mukurtu_migrate_results' => 'lists watchdog log entries, not translatable content',
    'og_members_overview' => 'og_membership has no routable display and is not translatable content',

    // Genuinely visitor-facing, not yet migrated to the fallback pattern.
    // Tracked under #1159 / #1188 (Phase 4 of the #1260 multilingual
    // roadmap). Remove each line as its view is migrated. (11 plain-entity
    // views - mukurtu_categories, mukurtu_collection_items,
    // mukurtu_recent_content, browse_by_community, taxonomy_term, the 3
    // entity_browser pickers, and the 3 audience-resolved views - migrated
    // in a follow-up PR; the remaining 14 below are Search API-backed and
    // depend on the language_with_fallback index field.)
    'mukurtu_browse' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_browse_by_map' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_browse_collections' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_browse_map' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_digital_heritage_browse' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_dictionary' => 'visitor-facing, not yet migrated - #1159/#1188',
    'mukurtu_taxonomy_references' => 'visitor-facing, not yet migrated - #1159/#1188',
    // The mukurtu_solr module is not installed by default (see mukurtu.install)
    // - these are a dormant alternate-backend copy of the views above.
    'dictionary_browse_solr_new_index' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_browse_by_map_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_browse_collections_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_browse_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_dictionary_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_digital_heritage_browse_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
    'mukurtu_taxonomy_references_solr' => 'visitor-facing, not yet migrated - #1159/#1188 (mukurtu_solr, not installed by default)',
  ];

  /**
   * Every visitor-facing, non-exempt view implements the fallback policy.
   */
  public function testEveryViewImplementsLanguageFallbackOrIsExempt(): void {
    $profileRoot = dirname(__DIR__, 5);
    $this->assertDirectoryExists($profileRoot . '/modules', 'Sanity check: resolved profile root is wrong.');

    $fallbackFieldsByIndex = $this->getSearchApiFallbackFields($profileRoot);

    $gaps = [];
    foreach ($this->globConfig($profileRoot, 'views.view.*.yml') as $path) {
      $view = Yaml::parseFile($path);
      $viewId = $view['id'] ?? basename($path, '.yml');

      if (isset(self::EXEMPTIONS[$viewId])) {
        continue;
      }
      if ($this->viewImplementsFallback($view, $fallbackFieldsByIndex)) {
        continue;
      }
      $gaps[$viewId] = $path;
    }

    $message = "The following views implement neither language-fallback mechanism from "
      . "docs/content-language-policy.md and have no entry in "
      . "ViewLanguageFallbackCoverageTest::EXEMPTIONS. Either implement the fallback "
      . "pattern for the view's backend, or add a documented exemption:\n"
      . implode("\n", array_map(fn ($id, $path) => "  $id ($path)", array_keys($gaps), $gaps));

    $this->assertSame([], $gaps, $message);
  }

  /**
   * Globs a config filename pattern across every install/optional location.
   */
  private function globConfig(string $profileRoot, string $pattern): array {
    return array_merge(
      glob("$profileRoot/config/install/$pattern"),
      glob("$profileRoot/config/optional/$pattern"),
      glob("$profileRoot/modules/*/config/install/$pattern"),
      glob("$profileRoot/modules/*/config/optional/$pattern"),
    );
  }

  /**
   * Maps search_api index ID to field IDs whose property_path is
   * language_with_fallback.
   *
   * @return array<string, string[]>
   */
  private function getSearchApiFallbackFields(string $profileRoot): array {
    $fallbackFieldsByIndex = [];
    foreach ($this->globConfig($profileRoot, 'search_api.index.*.yml') as $path) {
      $index = Yaml::parseFile($path);
      $indexId = $index['id'] ?? basename($path, '.yml');
      foreach ($index['field_settings'] ?? [] as $fieldId => $fieldSettings) {
        if (($fieldSettings['property_path'] ?? NULL) === 'language_with_fallback') {
          $fallbackFieldsByIndex[$indexId][] = $fieldId;
        }
      }
    }
    return $fallbackFieldsByIndex;
  }

  /**
   * Whether any display of this view implements the correct fallback
   * mechanism for its backend.
   *
   * @param array<string, string[]> $fallbackFieldsByIndex
   */
  private function viewImplementsFallback(array $view, array $fallbackFieldsByIndex): bool {
    $baseTable = $view['base_table'] ?? '';
    $displays = $view['display'] ?? [];
    $defaultFilters = $displays['default']['display_options']['filters'] ?? [];

    if (str_starts_with($baseTable, 'search_api_index_')) {
      $indexId = substr($baseTable, strlen('search_api_index_'));
      $fallbackFields = $fallbackFieldsByIndex[$indexId] ?? [];
      if (!$fallbackFields) {
        return FALSE;
      }
      foreach ($displays as $display) {
        foreach ($this->effectiveFilters($display, $defaultFilters) as $filter) {
          if (in_array($filter['field'] ?? NULL, $fallbackFields, TRUE)) {
            return TRUE;
          }
        }
      }
      return FALSE;
    }

    foreach ($displays as $display) {
      $hasDefaultLangcodeFilter = FALSE;
      foreach ($this->effectiveFilters($display, $defaultFilters) as $filter) {
        if (($filter['plugin_id'] ?? NULL) === 'boolean'
          && ($filter['field'] ?? NULL) === 'default_langcode'
          && (string) ($filter['value'] ?? '') === '1'
        ) {
          $hasDefaultLangcodeFilter = TRUE;
          break;
        }
      }
      $renderingLanguage = $display['display_options']['rendering_language'] ?? NULL;
      if ($hasDefaultLangcodeFilter && $renderingLanguage === '***LANGUAGE_language_content***') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * A display's own filters, or the default display's if inherited.
   *
   * Views config only carries a display's own `filters` key when
   * `defaults.filters` is explicitly FALSE for that display; otherwise the
   * filters are inherited from the `default` display.
   */
  private function effectiveFilters(array $display, array $defaultFilters): array {
    $options = $display['display_options'] ?? [];
    $overridden = ($options['defaults']['filters'] ?? TRUE) === FALSE;
    return $overridden ? ($options['filters'] ?? []) : $defaultFilters;
  }

}
