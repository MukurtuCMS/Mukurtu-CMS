<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_landing_page_update_40007().
 *
 * The hook widens two allowlists on an existing site. The happy path matters
 * less than the guard rails: an allowlist key that is absent means the
 * category is unrestricted, so writing one would take block availability away
 * rather than grant it. These tests pin that behaviour.
 */
#[Group('mukurtu_landing_page')]
class LandingPageRestrictionsUpdateTest extends KernelTestBase {

  protected const DISPLAY = 'core.entity_view_display.node.landing_page.default';

  protected const LEGACY_VIEW = 'views_block:browse_by_community-community_browse_block';

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
      array_unshift($allowlists['Lists (Views)'], self::LEGACY_VIEW);
    });
  }

  /**
   * On a 4.0.1 site the hook grants both sets of blocks.
   */
  public function testUpdatesPreFixSite(): void {
    $this->writePreFixDisplay();

    $before = $this->allowlists();
    $this->assertSame([], array_intersect(self::HEROES, $before['Inline blocks']));
    $this->assertArrayNotHasKey('Mukurtu', $before);

    mukurtu_landing_page_update_40007();

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
   * A site that already dropped the legacy view is not disturbed.
   */
  public function testLeavesAnAlreadyCleanViewsAllowlistAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      unset($restrictions['entity_view_mode_restriction']['allowlisted_blocks']['Mukurtu']);
    });

    $before = $this->allowlists()['Lists (Views)'];
    mukurtu_landing_page_update_40007();

    $this->assertSame($before, $this->allowlists()['Lists (Views)']);
  }

  /**
   * Running twice changes nothing the second time.
   */
  public function testIsIdempotent(): void {
    $this->writePreFixDisplay();

    mukurtu_landing_page_update_40007();
    $after_first = $this->allowlists();

    $message = mukurtu_landing_page_update_40007();

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

    mukurtu_landing_page_update_40007();

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

    mukurtu_landing_page_update_40007();

    $this->assertArrayNotHasKey('Mukurtu', $this->allowlists());
  }

  /**
   * A site with no restrictions at all is left alone.
   */
  public function testLeavesAnUnrestrictedSiteAlone(): void {
    $this->writeDisplay(static function (array &$restrictions): void {
      $restrictions['entity_view_mode_restriction'] = [];
    });

    $message = mukurtu_landing_page_update_40007();

    $this->assertSame([], $this->allowlists());
    $this->assertStringContainsString('not configured', $message);
  }

}
