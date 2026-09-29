<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\Entity\PathautoPattern;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_landing_page_update_40202(), which fixes the pattern's bundle.
 *
 * The shipped Landing Pages URL alias pattern was copied from the Basic Pages
 * one and never had its bundle changed, so it matched page nodes instead of
 * landing_page nodes (#2208). The hook rewrites that exact shipped condition
 * on existing sites.
 *
 * @see mukurtu_landing_page_update_40202()
 */
#[Group('mukurtu_landing_page')]
class LandingPagesPatternBundleUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'path', 'path_alias', 'token', 'pathauto'];

  /**
   * {@inheritdoc}
   *
   * The shipped pattern carries a uuid key inside its condition (as any
   * condition-plugin collection does) that pathauto's config schema does not
   * declare. That is fine at runtime but trips the test-only strict checker;
   * the fixture mirrors the real config rather than dropping the key.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');

    // Required directly rather than via loadInclude(): mukurtu_landing_page is
    // not enabled here, and loadInclude() silently does nothing for a
    // profile-nested module the kernel does not know about.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_landing_page');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_landing_page.install';
  }

  /**
   * Creates the landing_pages pattern with the given bundle condition.
   */
  private function makePattern(array $bundles): PathautoPattern {
    $pattern = PathautoPattern::create([
      'id' => 'landing_pages',
      'label' => 'Landing Pages',
      'type' => 'canonical_entities:node',
      'pattern' => '[node:title]',
      'weight' => -5,
      'selection_criteria' => [
        '130468a8-2a50-48dc-abf0-a7b851c5ce29' => [
          'id' => 'entity_bundle:node',
          'negate' => FALSE,
          'uuid' => '130468a8-2a50-48dc-abf0-a7b851c5ce29',
          'context_mapping' => ['node' => 'node'],
          'bundles' => $bundles,
        ],
      ],
    ]);
    $pattern->save();
    return PathautoPattern::load('landing_pages');
  }

  /**
   * Reads back the bundle condition from the saved pattern.
   */
  private function savedBundles(): array {
    $criteria = PathautoPattern::load('landing_pages')->get('selection_criteria');
    return reset($criteria)['bundles'];
  }

  /**
   * The shipped, wrong "page" bundle is rewritten to landing_page.
   */
  public function testRewritesShippedPageBundle(): void {
    $this->makePattern(['page' => 'page']);

    $message = mukurtu_landing_page_update_40202();

    $this->assertSame(['landing_page' => 'landing_page'], $this->savedBundles());
    $this->assertStringContainsString('landing_page', (string) $message);
    $this->assertStringContainsString('next saved', (string) $message, 'The operator was not told existing URLs are left alone.');
  }

  /**
   * Running it twice is harmless and reports nothing changed the second time.
   */
  public function testIsIdempotent(): void {
    $this->makePattern(['page' => 'page']);

    mukurtu_landing_page_update_40202();
    $second = mukurtu_landing_page_update_40202();

    $this->assertSame(['landing_page' => 'landing_page'], $this->savedBundles());
    $this->assertStringContainsString('nothing to update', (string) $second);
  }

  /**
   * A pattern a site has customised is left alone.
   *
   * Only the exact shipped value is rewritten. Anything else means an admin
   * has deliberately changed the condition, and the hook must not clobber it.
   */
  public function testLeavesCustomisedPatternAlone(): void {
    $custom = ['page' => 'page', 'article' => 'article'];
    $this->makePattern($custom);

    $message = mukurtu_landing_page_update_40202();

    $this->assertSame($custom, $this->savedBundles());
    $this->assertStringContainsString('nothing to update', (string) $message);
  }

  /**
   * A site without the pattern does not error.
   */
  public function testMissingPatternDoesNotError(): void {
    $this->assertNull(PathautoPattern::load('landing_pages'));

    $message = mukurtu_landing_page_update_40202();

    $this->assertStringContainsString('nothing to update', (string) $message);
  }

}
