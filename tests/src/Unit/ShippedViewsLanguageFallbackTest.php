<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts the content-language settings every shipped view depends on.
 *
 * Sites with more than one language rely on two mechanisms, and a view that
 * loses either one silently starts showing the wrong translation rather than
 * failing outright, which is why these are worth pinning:
 *
 * - Search API backed views filter on "language_with_fallback", a processor
 *   derived index property. They must not also carry the stock
 *   "search_api_language" filter, which matches the raw indexed langcode and
 *   would exclude items that only exist in a fallback language.
 * - Entity backed views filter on "default_langcode" and render with
 *   "***LANGUAGE_language_content***" so rows follow the content language
 *   rather than the interface language.
 *
 * This replaces twelve near-identical kernel tests that each stripped the keys
 * out of a shipped view, ran an update hook and asserted the hook put them
 * back. With the update hooks gone in 4.0.1 the shipped config is the only
 * thing left to protect, so the assertions now read it directly. That is also
 * the stronger check: the old tests could pass while the shipped file itself
 * was wrong, because they overwrote it before asserting.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 *
 * @see docs/content-language-policy.md
 */
#[Group('mukurtu')]
#[Group('mukurtu_multilingual')]
class ShippedViewsLanguageFallbackTest extends UnitTestCase {

  /**
   * Views whose display deliberately ignores the content-language policy.
   *
   * The notifications admin page is an administrative listing: it shows every
   * message regardless of translation, so filtering it by content language
   * would hide rows from an administrator.
   */
  private const POLICY_EXEMPT = [
    'mukurtu_message_log' => ['mukurtu_notifications_admin_page'],
  ];

  /**
   * Every shipped view carrying content-language configuration.
   *
   * Generated from the shipped files rather than transcribed by hand.
   */
  private static function expectations(): array {
    return [
      'mukurtu_categories' => [
        'file' => 'config/install/views.view.mukurtu_categories.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['default', 'browse_by_category_block', 'categories_page'],
      ],
      'mukurtu_browse' => [
        'file' => 'modules/mukurtu_browse/config/install/views.view.mukurtu_browse.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_by_map' => [
        'file' => 'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_by_map.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_collections' => [
        'file' => 'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_collections.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_map' => [
        'file' => 'modules/mukurtu_browse/config/install/views.view.mukurtu_browse_map.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_digital_heritage_browse' => [
        'file' => 'modules/mukurtu_browse/config/install/views.view.mukurtu_digital_heritage_browse.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_collection_items' => [
        'file' => 'modules/mukurtu_collection/config/install/views.view.mukurtu_collection_items.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['mukurtu_collection_items_block', 'mukurtu_collection_items_block_grid', 'mukurtu_personal_collection_items_block', 'mukurtu_personal_collection_items_block_grid', 'mukurtu_collection_items_block_map', 'mukurtu_personal_collection_items_block_map'],
      ],
      'my_personal_collections' => [
        'file' => 'modules/mukurtu_collection/config/install/views.view.my_personal_collections.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['my_personal_collections_block'],
      ],
      'mukurtu_content_browser' => [
        'file' => 'modules/mukurtu_core/config/install/views.view.mukurtu_content_browser.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['entity_browser', 'entity_browser_dictionary_word', 'entity_browser_person', 'entity_browser_collection'],
      ],
      'mukurtu_dictionary' => [
        'file' => 'modules/mukurtu_dictionary/config/install/views.view.mukurtu_dictionary.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'multipage_item_browser' => [
        'file' => 'modules/mukurtu_multipage_items/config/install/views.view.multipage_item_browser.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['entity_browser'],
      ],
      'mukurtu_message_log' => [
        'file' => 'modules/mukurtu_notifications/config/install/views.view.mukurtu_message_log.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['mukurtu_notifications_page'],
        'rendering_language' => ['mukurtu_notifications_page'],
      ],
      'mukurtu_recent_content' => [
        'file' => 'modules/mukurtu_notifications/config/install/views.view.mukurtu_recent_content.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['all_recent_content_block', 'user_recent_content_block'],
      ],
      'browse_by_community' => [
        'file' => 'modules/mukurtu_protocol/config/install/views.view.browse_by_community.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['default', 'community_browse_block'],
      ],
      'mukurtu_community_select' => [
        'file' => 'modules/mukurtu_protocol/config/install/views.view.mukurtu_community_select.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['mukurtu_community_select'],
      ],
      'mukurtu_people' => [
        'file' => 'modules/mukurtu_protocol/config/install/views.view.mukurtu_people.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => [],
      ],
      'user_admin_people' => [
        'file' => 'modules/mukurtu_protocol/config/install/views.view.user_admin_people.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => [],
      ],
      'dictionary_browse_solr_new_index' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.dictionary_browse_solr_new_index.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_by_map_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_by_map_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_collections_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_collections_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_browse_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_browse_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_dictionary_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_dictionary_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_digital_heritage_browse_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_digital_heritage_browse_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_taxonomy_references_solr' => [
        'file' => 'modules/mukurtu_solr/config/install/views.view.mukurtu_taxonomy_references_solr.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'mukurtu_taxonomy_references' => [
        'file' => 'modules/mukurtu_taxonomy/config/install/views.view.mukurtu_taxonomy_references.yml',
        'language_with_fallback' => ['default'],
        'default_langcode' => [],
        'rendering_language' => [],
      ],
      'taxonomy_term' => [
        'file' => 'modules/mukurtu_taxonomy/config/install/views.view.taxonomy_term.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['default'],
        'rendering_language' => ['default', 'feed_1', 'page_1'],
      ],
      'mukurtu_workflow_overview' => [
        'file' => 'modules/mukurtu_workflows/config/install/views.view.mukurtu_workflow_overview.yml',
        'language_with_fallback' => [],
        'default_langcode' => ['my_content_in_progress', 'my_content_published', 'review_queue'],
        'rendering_language' => ['my_content_in_progress', 'my_content_published', 'review_queue'],
      ],
    ];
  }

  /**
   * Data provider: one case per shipped view.
   */
  public static function viewProvider(): \Generator {
    foreach (self::expectations() as $name => $spec) {
      yield $name => [$name, $spec];
    }
  }

  /**
   * Resolves the profile root from this file's location.
   */
  private function profileRoot(): string {
    $root = dirname(__DIR__, 3);
    $this->assertFileExists("$root/mukurtu.info.yml", 'Sanity check: resolved profile root is wrong.');
    return $root;
  }

  #[DataProvider('viewProvider')]
  public function testShippedViewCarriesItsLanguageSettings(string $name, array $spec): void {
    $path = $this->profileRoot() . '/' . $spec['file'];
    $this->assertFileExists($path);

    $view = Yaml::parseFile($path);
    $displays = $view['display'] ?? [];
    $this->assertNotEmpty($displays, "$name: view has no displays.");

    foreach ($spec['language_with_fallback'] as $display) {
      $filters = $displays[$display]['display_options']['filters'] ?? [];
      $this->assertArrayHasKey(
        'language_with_fallback',
        $filters,
        "$name display '$display' lost its language_with_fallback filter, so it will no longer fall back to another translation."
      );
    }

    foreach ($spec['default_langcode'] as $display) {
      $filters = $displays[$display]['display_options']['filters'] ?? [];
      $this->assertArrayHasKey(
        'default_langcode',
        $filters,
        "$name display '$display' lost its default_langcode filter, so translated rows will duplicate."
      );
    }

    foreach ($spec['rendering_language'] as $display) {
      $this->assertSame(
        '***LANGUAGE_language_content***',
        $displays[$display]['display_options']['rendering_language'] ?? NULL,
        "$name display '$display' no longer renders in the content language."
      );
    }
  }

  /**
   * The stock Search API language filter must not come back.
   *
   * It matches the raw indexed langcode, so pairing it with
   * language_with_fallback would filter out exactly the fallback rows the
   * other filter exists to include.
   */
  #[DataProvider('viewProvider')]
  public function testSearchApiLanguageFilterIsNotUsed(string $name, array $spec): void {
    $view = Yaml::parseFile($this->profileRoot() . '/' . $spec['file']);

    foreach ($view['display'] ?? [] as $display => $definition) {
      $this->assertArrayNotHasKey(
        'search_api_language',
        $definition['display_options']['filters'] ?? [],
        "$name display '$display' uses search_api_language, which defeats language_with_fallback."
      );
    }
  }

  /**
   * Administrative displays stay outside the content-language policy.
   */
  public function testPolicyExemptDisplaysAreNotFiltered(): void {
    foreach (self::POLICY_EXEMPT as $name => $exemptDisplays) {
      $spec = self::expectations()[$name];
      $view = Yaml::parseFile($this->profileRoot() . '/' . $spec['file']);

      foreach ($exemptDisplays as $display) {
        $this->assertArrayHasKey($display, $view['display'], "$name: exempt display '$display' no longer exists, so this exemption is stale.");
        $this->assertArrayNotHasKey(
          'default_langcode',
          $view['display'][$display]['display_options']['filters'] ?? [],
          "$name display '$display' is an administrative listing and must not be filtered by content language."
        );
      }
    }
  }

}
