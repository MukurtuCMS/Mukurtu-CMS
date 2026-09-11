<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Pins display-level details of shipped views, browsers and view displays.
 *
 * These are the leftovers of several update hooks that each fixed one visible
 * detail: a search box with no placeholder, admin links leaking into a public
 * block, an export action listed twice, an entity browser rendering in the
 * front-end theme, and audio players in the wrong view mode. None of them
 * would fail loudly on a fresh install, they would just look wrong, which is
 * why they are worth pinning now the hooks that set them are gone.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
class ShippedDisplayConfigTest extends UnitTestCase {

  /**
   * Resolves the profile root from this file's location.
   */
  private function profileRoot(): string {
    $root = dirname(__DIR__, 3);
    $this->assertFileExists("$root/mukurtu.info.yml", 'Sanity check: resolved profile root is wrong.');
    return $root;
  }

  /**
   * Parses a shipped config file.
   */
  private function shipped(string $file): array {
    $path = $this->profileRoot() . '/' . $file;
    $this->assertFileExists($path);
    return Yaml::parseFile($path);
  }

  /**
   * Exposed search boxes and the placeholder each shows.
   */
  public static function placeholderProvider(): \Generator {
    yield 'browse' => [
      'modules/mukurtu_browse/config/install/views.view.mukurtu_browse.yml',
      'Search all content',
    ];
    yield 'digital heritage browse' => [
      'modules/mukurtu_browse/config/install/views.view.mukurtu_digital_heritage_browse.yml',
      'Search digital heritage items',
    ];
    yield 'dictionary' => [
      'modules/mukurtu_dictionary/config/install/views.view.mukurtu_dictionary.yml',
      'Search dictionary entries',
    ];
  }

  /**
   * Each search box keeps its placeholder.
   *
   * An empty search box gives no clue what it searches, which matters most on
   * the browse pages where several similar boxes exist across the site.
   */
  #[DataProvider('placeholderProvider')]
  public function testSearchBoxKeepsItsPlaceholder(string $file, string $expected): void {
    $view = $this->shipped($file);

    $this->assertSame(
      $expected,
      $view['display']['default']['display_options']['filters']['search_api_fulltext']['expose']['placeholder'] ?? NULL,
      "$file lost its search placeholder."
    );
  }

  /**
   * The public category block does not render contextual admin links.
   *
   * The block appears on the front end for anonymous visitors, where the admin
   * links are both useless and a hint at the editing UI.
   */
  public function testCategoryBlockHidesAdminLinks(): void {
    $view = $this->shipped('config/install/views.view.mukurtu_categories.yml');

    $this->assertFalse(
      $view['display']['browse_by_category_block']['display_options']['show_admin_links'] ?? NULL,
      'The category browse block would render contextual admin links.'
    );
  }

  /**
   * The manage-content bulk form lists each export action once.
   *
   * Actions may legitimately repeat under different preconfiguration - the
   * moderation state action ships three times, one per target state - so this
   * checks for entries that are duplicates in full, not merely a repeated
   * action id.
   */
  public function testBulkFormHasNoDuplicateActions(): void {
    $view = $this->shipped('config/install/views.view.mukurtu_manage_all_content.yml');
    $actions = $view['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['selected_actions'] ?? [];
    $this->assertNotEmpty($actions, 'The bulk form lists no actions at all.');

    $addToList = array_filter(
      $actions,
      static fn (array $action): bool => ($action['action_id'] ?? NULL) === 'mukurtu_export_add_to_list_action'
    );
    $this->assertCount(1, $addToList, 'The "add to export list" action is listed more than once.');

    $serialised = array_map(
      static fn (array $action): string => json_encode($action),
      $actions
    );
    $this->assertSame(
      array_values(array_unique($serialised)),
      array_values($serialised),
      'The bulk form lists an identical action twice, which shows the operator a duplicate entry.'
    );
  }

  /**
   * Entity browsers render in the admin theme.
   *
   * A browser opens in a modal over the node form. Rendering it in the front
   * end theme means it inherits site styling that was never designed for a
   * dense selection UI.
   */
  #[DataProvider('entityBrowserProvider')]
  public function testEntityBrowsersUseTheAdminTheme(string $file): void {
    $browser = $this->shipped($file);

    $this->assertTrue(
      $browser['display_configuration']['use_admin_theme'] ?? NULL,
      "$file would render in the front-end theme."
    );
  }

  public static function entityBrowserProvider(): \Generator {
    yield 'content browser' => ['modules/mukurtu_core/config/install/entity_browser.browser.mukurtu_content_browser.yml'];
    yield 'collection browser' => ['modules/mukurtu_core/config/install/entity_browser.browser.mukurtu_collection_browser.yml'];
  }

  /**
   * Sample sentence recordings render as players, not as links.
   *
   * The default display uses the dictionary-specific audio view mode and the
   * teaser a lighter one. Either falling back to the stock 'default' view mode
   * renders the media entity's own page teaser instead of an audio player.
   */
  #[DataProvider('sampleSentenceProvider')]
  public function testSampleSentenceRecordingsRenderAsAudio(string $file, string $expectedViewMode): void {
    $display = $this->shipped($file);
    $settings = $display['content']['field_sentence_recording']['settings'] ?? NULL;

    $this->assertIsArray($settings, "$file no longer configures field_sentence_recording.");
    $this->assertSame($expectedViewMode, $settings['view_mode'] ?? NULL, "$file renders the recording in the wrong view mode.");
    $this->assertFalse($settings['link'] ?? NULL, "$file renders the recording as a link rather than a player.");
  }

  public static function sampleSentenceProvider(): \Generator {
    // The default display ships with the dictionary module, the teaser with
    // the profile itself. That split is easy to miss when editing one of them.
    yield 'default' => [
      'modules/mukurtu_dictionary/config/install/core.entity_view_display.paragraph.sample_sentence.default.yml',
      'audio_for_dictionary_word',
    ];
    yield 'teaser' => [
      'config/install/core.entity_view_display.paragraph.sample_sentence.teaser.yml',
      'browse',
    ];
  }

}
