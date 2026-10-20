<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionStorageInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which blocks the landing page display offers in Layout Builder.
 *
 * Asserting that the shipped YAML contains the right allowlist entries is not
 * enough: Layout Builder Restrictions decides availability from a combination
 * of allowlisted_blocks, denylisted_blocks, restricted_categories *and*
 * allowed_block_categories, and the interaction between those is exactly what
 * was wrong before. So run the shipped config through the real restriction
 * plugin and assert on what survives.
 */
#[Group('mukurtu_landing_page')]
class LandingPageBlockRestrictionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The block_content module is deliberately absent. EntityViewModeRestriction
   * only queries the block_content tables when that module is installed, so
   * leaving it out keeps this test off the database without changing any of the
   * code paths exercised here. The one test that needs real content blocks
   * enables it for itself.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'block',
    'layout_discovery',
    'layout_builder',
    'layout_builder_restrictions',
  ];

  /**
   * The restriction settings as shipped by mukurtu_landing_page.
   */
  protected array $thirdPartySettings;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Read the shipped display straight from config/install rather than
    // installing it. The entity carries a long dependency tail (pathauto,
    // language, the block_content bundles) that has no bearing on the
    // restriction logic under test.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_landing_page');
    $storage = new FileStorage($module_path . '/config/install');
    $display = $storage->read('core.entity_view_display.node.landing_page.default');
    $this->assertIsArray($display, 'The landing page default view display ships in config/install.');

    $this->thirdPartySettings = $display['third_party_settings']['layout_builder_restrictions'];
  }

  /**
   * Runs block definitions through the restriction plugin as configured.
   *
   * @param array $definitions
   *   Block definitions keyed by plugin ID, each with a category and provider.
   *
   * @return string[]
   *   The plugin IDs that survived filtering.
   */
  protected function filterDefinitions(array $definitions): array {
    $settings = $this->thirdPartySettings;

    // alterBlockDefinitions() reads the restrictions off the section storage's
    // default, which only has to be a ThirdPartySettingsInterface - it never
    // touches the rest of SectionStorageInterface here. Mocking the pair keeps
    // the test on the restriction logic instead of Layout Builder's storage
    // plumbing.
    $section_storage = $this->createMockForIntersectionOfInterfaces([
      SectionStorageInterface::class,
      ThirdPartySettingsInterface::class,
    ]);
    $section_storage->method('getThirdPartySetting')
      ->willReturnCallback(static fn(string $module, string $key, $default = NULL) => $settings[$key] ?? $default);

    $plugin = \Drupal::service('plugin.manager.layout_builder_restriction')
      ->createInstance('entity_view_mode_restriction');

    $filtered = $plugin->alterBlockDefinitions($definitions, [
      'section_storage' => $section_storage,
    ]);

    return array_keys($filtered);
  }

  /**
   * The hero bundles must be creatable as inline blocks, not just placeable.
   *
   * DefaultLandingPage pre-creates them as reusable content blocks, which is
   * why nobody noticed they were missing from the inline block allowlist: a
   * site builder could place the seeded ones but never build a new hero.
   */
  public function testHeroBundlesAreAvailableAsInlineBlocks(): void {
    $heroes = [
      'inline_block:full_image_with_description',
      'inline_block:image_with_description',
      'inline_block:vertical_image_with_description',
    ];

    $definitions = [];
    foreach ($heroes as $id) {
      $definitions[$id] = ['category' => 'Inline blocks', 'provider' => 'layout_builder'];
    }
    // A bundle that is deliberately not offered on landing pages, to prove the
    // allowlist is still doing its job rather than letting everything through.
    $definitions['inline_block:mukurtu_footer'] = ['category' => 'Inline blocks', 'provider' => 'layout_builder'];

    $allowed = $this->filterDefinitions($definitions);

    foreach ($heroes as $id) {
      $this->assertContains($id, $allowed, "$id should be available as an inline block on landing pages.");
    }
    $this->assertNotContains('inline_block:mukurtu_footer', $allowed, 'The inline block allowlist should still exclude bundles it does not name.');
  }

  /**
   * Browse by Community must be re-addable after a site builder removes it.
   *
   * It ships on the default homepage, but DefaultLandingPage writes it into
   * the layout directly, which bypasses these restrictions. Before the fix the
   * "Mukurtu" category was absent from allowed_block_categories, which
   * EntityViewModeRestriction enforces as a fallback, so the block vanished
   * from the Layout UI and could never be put back.
   */
  public function testBrowseByCommunityIsAvailable(): void {
    $definitions = [
      'mukurtu_browse_by_community' => ['category' => 'Mukurtu', 'provider' => 'mukurtu_protocol'],
    ];

    $this->assertContains(
      'mukurtu_browse_by_community',
      $this->filterDefinitions($definitions),
      'Browse by Community should be addable through the Layout UI.'
    );
  }

  /**
   * Opening up the Mukurtu category must not drag the other blocks in with it.
   *
   * These three are a node-context sidebar list, a search-page toggle and the
   * dashboard setup checklist. None belongs on a landing page, so the fix
   * allowlists one block rather than the whole category.
   */
  public function testOtherMukurtuBlocksStayUnavailable(): void {
    $definitions = [
      'mukurtu_community_protocol_list' => ['category' => 'Mukurtu', 'provider' => 'mukurtu_protocol'],
      'mukurtu_search_collapse_toggle' => ['category' => 'Mukurtu', 'provider' => 'mukurtu_browse'],
      'mukurtu_site_setup_checklist' => ['category' => 'Mukurtu CMS', 'provider' => 'mukurtu_setup'],
    ];

    $allowed = $this->filterDefinitions($definitions);

    $this->assertSame([], $allowed, 'Only Browse by Community should be offered from the Mukurtu categories.');
  }

  /**
   * The "Custom" plugin blocks must not be offered on landing pages.
   *
   * Layout Builder Restrictions renames "Custom" to "Custom blocks" before
   * checking restricted_categories, so the config has to use the new name.
   * The categories must be TranslatableMarkup, as real plugin definitions
   * are: a plain string skips the rename and would hide the bug.
   */
  public function testCustomCategoryIsUnavailable(): void {
    $definitions = [
      'consent_popup' => ['category' => new TranslatableMarkup('Custom'), 'provider' => 'consent_popup'],
      'mukurtu_footer' => ['category' => new TranslatableMarkup('Custom'), 'provider' => 'mukurtu_footer'],
    ];

    $this->assertSame([], $this->filterDefinitions($definitions));
  }

  /**
   * The footer content block is hidden, other reusable blocks are not.
   *
   * The footer is placed in the footer region by the mukurtu_footer plugin.
   * Offering the entity in the "Content block" category put a second
   * "Mukurtu Footer" next to the homepage hero blocks.
   */
  public function testFooterContentBlockIsUnavailable(): void {
    $this->enableModules(['block_content', 'text', 'filter']);
    $this->installEntitySchema('block_content');

    $blocks = [];
    foreach (['mukurtu_footer', 'image_with_description'] as $bundle) {
      BlockContentType::create(['id' => $bundle, 'label' => $bundle])->save();
      $block = BlockContent::create(['type' => $bundle, 'info' => $bundle]);
      $block->save();
      $blocks[$bundle] = 'block_content:' . $block->uuid();
    }

    $definitions = [];
    foreach ($blocks as $plugin_id) {
      $definitions[$plugin_id] = ['category' => new TranslatableMarkup('Content block'), 'provider' => 'block_content'];
    }

    $allowed = $this->filterDefinitions($definitions);

    $this->assertNotContains($blocks['mukurtu_footer'], $allowed);
    $this->assertContains($blocks['image_with_description'], $allowed);
  }

  /**
   * The views blocks the default homepage ships with must stay available.
   */
  public function testHomepageViewsBlocksAreAvailable(): void {
    $definitions = [
      'views_block:mukurtu_browse_by_map-map_block' => ['category' => 'Lists (Views)', 'provider' => 'views'],
      'views_block:mukurtu_categories-browse_by_category_block' => [
        'category' => 'Lists (Views)',
        'provider' => 'views',
      ],
      'views_block:mukurtu_categories-browse_by_category_names_block' => [
        'category' => 'Lists (Views)',
        'provider' => 'views',
      ],
      'views_block:content_recent-block_1' => ['category' => 'Lists (Views)', 'provider' => 'views'],
    ];

    $allowed = $this->filterDefinitions($definitions);

    $this->assertCount(3, $allowed);
    $this->assertNotContains('views_block:content_recent-block_1', $allowed);
  }

  /**
   * The legacy community view must not be offered alongside the block plugin.
   *
   * The browse_by_community view filters on published status and langcode
   * only. It has no visibility filter, so it lists communities whose sharing
   * setting is "community-only" to everyone - the bug fixed for the block
   * plugin in issue #1005. Offering both also put two identically labelled
   * "Browse by Community" entries in the Add block list.
   */
  public function testLegacyCommunityViewIsNotOffered(): void {
    $definitions = [
      'views_block:browse_by_community-community_browse_block' => ['category' => 'Lists (Views)', 'provider' => 'views'],
      'mukurtu_browse_by_community' => ['category' => 'Mukurtu', 'provider' => 'mukurtu_protocol'],
    ];

    $allowed = $this->filterDefinitions($definitions);

    $this->assertSame(['mukurtu_browse_by_community'], $allowed, 'Only the visibility-aware block plugin should be offered.');
  }

}
