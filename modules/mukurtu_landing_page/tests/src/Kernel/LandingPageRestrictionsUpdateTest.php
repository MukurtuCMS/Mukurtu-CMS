<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_landing_page_update_40203(), 40204() and 40205().
 *
 * The hooks widen allowlists on an existing site. The happy path matters
 * less than the guard rails: an allowlist key that is absent means the
 * category is unrestricted, so writing one would take block availability away
 * rather than grant it. These tests pin that behaviour.
 */
#[Group('mukurtu_landing_page')]
class LandingPageRestrictionsUpdateTest extends KernelTestBase {

  protected const DISPLAY = 'core.entity_view_display.node.landing_page.default';

  protected const LEGACY_VIEW = 'views_block:browse_by_community-community_browse_block';

  protected const NAMES_BLOCK = 'views_block:mukurtu_categories-browse_by_category_names_block';

  protected const HEROES = [
    'inline_block:full_image_with_description',
    'inline_block:image_with_description',
    'inline_block:vertical_image_with_description',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'block',
    'node',
    'text',
    'filter',
    'layout_discovery',
    'layout_builder',
    'layout_builder_restrictions',
    'views',
  ];

  /**
   * The shipped display config, used as the starting shape for each scenario.
   */
  protected array $shipped;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_landing_page');
    require_once $this->root . '/' . $module_path . '/mukurtu_landing_page.install';

    $storage = new FileStorage($module_path . '/config/install');
    $this->shipped = $storage->read(self::DISPLAY);
    $this->assertIsArray($this->shipped);
  }

  /**
   * Writes a display whose restriction settings are built by the callback.
   *
   * @param callable|null $alter
   *   Receives the shipped layout_builder_restrictions settings by reference.
   */
  protected function writeDisplay(?callable $alter = NULL): void {
    $restrictions = $this->shipped['third_party_settings']['layout_builder_restrictions'];
    if ($alter) {
      $alter($restrictions);
    }

    $data = $this->shipped;
    $data['third_party_settings']['layout_builder_restrictions'] = $restrictions;
    \Drupal::configFactory()->getEditable(self::DISPLAY)->setData($data)->save();
  }

  /**
   * Returns the current allowlists.
   */
  protected function allowlists(): array {
    return \Drupal::config(self::DISPLAY)
      ->get('third_party_settings.layout_builder_restrictions.entity_view_mode_restriction.allowlisted_blocks') ?? [];
  }

  /**
   * Rolls the shipped config back to how 4.0.1 shipped it.
   */
  protected function writePreFixDisplay(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      $allowlists = &$restrictions['entity_view_mode_restriction']['allowlisted_blocks'];
      $allowlists['Inline blocks'] = array_values(array_diff($allowlists['Inline blocks'], self::HEROES));
      unset($allowlists['Mukurtu']);
      $allowlists['Lists (Views)'] = array_values(array_diff($allowlists['Lists (Views)'], [self::NAMES_BLOCK]));
      array_unshift($allowlists['Lists (Views)'], self::LEGACY_VIEW);
    });
    $this->rollBackCustomRestrictions();
  }

  /**
   * Rolls the "Custom" restrictions back to how they shipped before 40205.
   */
  protected function rollBackCustomRestrictions(): void {
    $config = \Drupal::configFactory()->getEditable(self::DISPLAY);
    $key = 'third_party_settings.layout_builder_restrictions.entity_view_mode_restriction';
    $settings = $config->get($key);
    $settings['restricted_categories'] = array_values(array_diff($settings['restricted_categories'], ['Custom blocks']));
    $settings['restricted_categories'][] = 'Custom';
    $settings['denylisted_blocks'] = [];
    $config->set($key, $settings)->save();
  }

  /**
   * Returns the current entity_view_mode_restriction settings.
   */
  protected function restrictions(): array {
    return \Drupal::config(self::DISPLAY)
      ->get('third_party_settings.layout_builder_restrictions.entity_view_mode_restriction') ?? [];
  }

  /**
   * On a 4.0.1 site the hook grants both sets of blocks.
   */
  public function testUpdatesPreFixSite(): void {
    $this->writePreFixDisplay();

    $before = $this->allowlists();
    $this->assertSame([], array_intersect(self::HEROES, $before['Inline blocks']));
    $this->assertArrayNotHasKey('Mukurtu', $before);

    mukurtu_landing_page_update_40203();

    $after = $this->allowlists();
    foreach (self::HEROES as $hero) {
      $this->assertContains($hero, $after['Inline blocks']);
    }
    $this->assertSame(['mukurtu_browse_by_community'], $after['Mukurtu']);

    // The blocks that were already allowlisted must survive untouched.
    $this->assertContains('inline_block:basic', $after['Inline blocks']);
    $this->assertContains('inline_block:featured_content', $after['Inline blocks']);

    // The legacy community view goes, the other views blocks stay.
    $this->assertContains(self::LEGACY_VIEW, $before['Lists (Views)']);
    $this->assertNotContains(self::LEGACY_VIEW, $after['Lists (Views)']);
    $this->assertContains('views_block:mukurtu_browse_by_map-map_block', $after['Lists (Views)']);
    $this->assertContains('views_block:mukurtu_categories-browse_by_category_block', $after['Lists (Views)']);
  }

  /**
   * An updated site must land on exactly what a fresh install ships.
   *
   * The hooks and config/install are maintained separately, so they can
   * drift. This pins them together: whatever a 4.0.1 site ends up with after
   * the updates has to equal the allowlists in the shipped display.
   */
  public function testPostUpdateStateMatchesShippedConfig(): void {
    $this->writePreFixDisplay();

    mukurtu_landing_page_update_40203();
    mukurtu_landing_page_update_40204();
    mukurtu_landing_page_update_40205();

    $shipped = $this->shipped['third_party_settings']['layout_builder_restrictions']['entity_view_mode_restriction'];
    $this->assertSame($shipped['allowlisted_blocks'], $this->allowlists());
    $this->assertSame($shipped['denylisted_blocks'], $this->restrictions()['denylisted_blocks']);
    $this->assertEqualsCanonicalizing($shipped['restricted_categories'], $this->restrictions()['restricted_categories']);
  }

  /**
   * A site that already dropped the legacy view is not disturbed.
   */
  public function testLeavesAnAlreadyCleanViewsAllowlistAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Mukurtu']);
    });

    $before = $this->allowlists()['Lists (Views)'];
    mukurtu_landing_page_update_40203();

    $this->assertSame($before, $this->allowlists()['Lists (Views)']);
  }

  /**
   * Running twice changes nothing the second time.
   */
  public function testIsIdempotent(): void {
    $this->writePreFixDisplay();

    mukurtu_landing_page_update_40203();
    $after_first = $this->allowlists();

    $message = mukurtu_landing_page_update_40203();

    $this->assertSame($after_first, $this->allowlists());
    $this->assertStringContainsString('already up to date', $message);
  }

  /**
   * An absent "Inline blocks" key means unrestricted, so do not create one.
   *
   * Writing the key here would restrict a site that currently offers every
   * inline block down to the three the hook knows about.
   */
  public function testDoesNotRestrictSiteWithNoInlineAllowlist(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Inline blocks']);
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Mukurtu']);
    });

    mukurtu_landing_page_update_40203();

    $this->assertArrayNotHasKey('Inline blocks', $this->allowlists());
  }

  /**
   * A site that already opened the Mukurtu category keeps it wide open.
   *
   * Adding a single-block allowlist would narrow it, because an allowlisted
   * category is checked before the allowed_block_categories fallback.
   */
  public function testDoesNotNarrowAnAlreadyOpenMukurtuCategory(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Mukurtu']);
      $restrictions['allowed_block_categories'][] = 'Mukurtu';
    });

    mukurtu_landing_page_update_40203();

    $this->assertArrayNotHasKey('Mukurtu', $this->allowlists());
  }

  /**
   * A site with no restrictions at all is left alone.
   */
  public function testLeavesAnUnrestrictedSiteAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      $restrictions['entity_view_mode_restriction'] = [];
    });

    $message = mukurtu_landing_page_update_40203();

    $this->assertSame([], $this->allowlists());
    $this->assertStringContainsString('not configured', $message);
  }

  /**
   * Update 40204 offers the names-only category block, keeping the others.
   */
  public function testAddsNamesOnlyCategoryBlock(): void {
    $this->writePreFixDisplay();
    $before = $this->allowlists()['Lists (Views)'];
    $this->assertNotContains(self::NAMES_BLOCK, $before);

    $message = mukurtu_landing_page_update_40204();

    $after = $this->allowlists()['Lists (Views)'];
    $this->assertContains(self::NAMES_BLOCK, $after);
    $this->assertSame([], array_diff($before, $after));
    $this->assertStringContainsString('names only', $message);

    // Running it again changes nothing.
    $message = mukurtu_landing_page_update_40204();
    $this->assertSame($after, $this->allowlists()['Lists (Views)']);
    $this->assertStringContainsString('already up to date', $message);
  }

  /**
   * Update 40204 does not create a views allowlist where none exists.
   *
   * An absent key means every views block is offered, so writing one would
   * take the others away.
   */
  public function testNamesOnlyBlockLeavesUnrestrictedViewsAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Lists (Views)']);
    });

    mukurtu_landing_page_update_40204();

    $this->assertArrayNotHasKey('Lists (Views)', $this->allowlists());
  }

  /**
   * Update 40205 hides the Custom category and the footer content block.
   */
  public function testHidesCustomCategoryAndFooter(): void {
    $this->writeDisplay();
    $this->rollBackCustomRestrictions();
    $this->assertContains('Custom', $this->restrictions()['restricted_categories']);

    $message = mukurtu_landing_page_update_40205();

    $after = $this->restrictions();
    $this->assertNotContains('Custom', $after['restricted_categories']);
    $this->assertContains('Custom blocks', $after['restricted_categories']);
    $this->assertSame(['mukurtu_footer'], $after['denylisted_blocks']['Custom block types']);
    $this->assertStringContainsString('Custom block category', $message);
    $this->assertStringContainsString('Mukurtu Footer', $message);

    // Running it again changes nothing.
    $message = mukurtu_landing_page_update_40205();
    $this->assertSame($after, $this->restrictions());
    $this->assertStringContainsString('already up to date', $message);
  }

  /**
   * Update 40205 keeps block types a site builder already denylisted.
   */
  public function testFooterDenylistKeepsExistingEntries(): void {
    $this->writeDisplay();
    $this->rollBackCustomRestrictions();
    $config = \Drupal::configFactory()->getEditable(self::DISPLAY);
    $config->set('third_party_settings.layout_builder_restrictions.entity_view_mode_restriction.denylisted_blocks', [
      'Custom block types' => ['call_to_action'],
    ])->save();

    mukurtu_landing_page_update_40205();

    $this->assertSame(['call_to_action', 'mukurtu_footer'], $this->restrictions()['denylisted_blocks']['Custom block types']);
  }

  /**
   * Update 40205 leaves "Custom" alone on sites with per-block rules.
   *
   * With a "Custom blocks" allowlist or denylist, Layout Builder Restrictions
   * files every reusable content block under "Custom blocks" too. Restricting
   * that category would hide all of them, not just the plugin blocks.
   */
  public function testLeavesCustomAloneWithPerBlockRules(): void {
    $this->writeDisplay();
    $this->rollBackCustomRestrictions();
    $config = \Drupal::configFactory()->getEditable(self::DISPLAY);
    $config->set('third_party_settings.layout_builder_restrictions.entity_view_mode_restriction.allowlisted_blocks.Custom blocks', [
      'block_content:00000000-0000-0000-0000-000000000000',
    ])->save();

    mukurtu_landing_page_update_40205();

    $after = $this->restrictions()['restricted_categories'];
    $this->assertContains('Custom', $after);
    $this->assertNotContains('Custom blocks', $after);
  }

  /**
   * Update 40205 does not add the first restriction to an unrestricted site.
   */
  public function testCustomFixLeavesAnUnrestrictedSiteAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      $restrictions['entity_view_mode_restriction'] = [];
    });

    $message = mukurtu_landing_page_update_40205();

    $this->assertSame([], $this->restrictions());
    $this->assertStringContainsString('not configured', $message);
  }

}
