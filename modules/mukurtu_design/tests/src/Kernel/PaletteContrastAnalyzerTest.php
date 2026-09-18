<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the custom palette contrast check against the real stylesheet.
 *
 * Deliberately runs against the theme's compiled css/style.css rather than
 * a fixture. The point of deriving the colour pairs from the stylesheet is
 * that a hand-written list goes stale; a test against a fixture would go
 * stale in exactly the same way and hide it.
 *
 * @see \Drupal\mukurtu_design\PaletteContrastAnalyzer
 */
class PaletteContrastAnalyzerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'geofield', 'leaflet', 'mukurtu_core', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The analyzer under test.
   */
  protected $analyzer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['mukurtu_v4']);
    $this->analyzer = $this->container->get('mukurtu_design.palette_contrast_analyzer');
  }

  /**
   * Finds a hex value for a named custom property in a reported failure.
   */
  private function failureFor(array $failures, string $foreground, string $background): ?array {
    foreach ($failures as $failure) {
      if ($failure['foreground'] === $foreground && $failure['background'] === $background) {
        return $failure;
      }
    }
    return NULL;
  }

  /**
   * The palette that caused #2178 is reported, at the ratio that was measured.
   *
   * This is the regression this whole check exists for. The Blue and gold
   * values put brand primary dark text on the secondary colour at 2.45:1 in
   * the horizontal image-with-description panel. A custom palette using the
   * same two colours reproduces it, because the panel's text colour is
   * derived from brand primary dark and the custom palette cannot override
   * that derived property.
   */
  public function testCatchesTheIssue2178Pair(): void {
    $failures = $this->analyzer->findFailures([
      'brand_primary' => '#138aab',
      'brand_primary_dark' => '#107996',
      'brand_primary_accent' => '#159ec4',
      'brand_secondary' => '#e6ab49',
      'brand_secondary_dark' => '#9d6915',
      'brand_secondary_accent' => '#f1b85a',
    ]);

    $this->assertNotEmpty($failures, 'The palette that caused #2178 is reported as failing.');

    $failure = $this->failureFor($failures, '--image-description-horizontal-text', '--brand-secondary');
    $this->assertNotNull($failure, 'The horizontal image-with-description panel pair is among the failures.');
    $this->assertEqualsWithDelta(2.45, $failure['ratio'], 0.01);
    $this->assertSame(4.5, $failure['required']);
    $this->assertNotEmpty($failure['selectors']);
  }

  /**
   * The background can live on an ancestor rather than the same rule.
   *
   * The #2178 pair sets background-color on one selector and the heading
   * colour on a descendant in a separate rule, so a check that only looked
   * inside a single declaration block would miss it entirely. This asserts
   * the reported selector really is a descendant of the one carrying the
   * background, rather than the pair being found some other way.
   */
  public function testResolvesBackgroundsFromAncestorSelectors(): void {
    $failures = $this->analyzer->findFailures([
      'brand_primary_dark' => '#107996',
      'brand_secondary' => '#e6ab49',
    ]);

    $failure = $this->failureFor($failures, '--image-description-horizontal-text', '--brand-secondary');
    $this->assertNotNull($failure);
    $this->assertStringContainsString(
      '.block--image-with-description--horizontal .block-wrapper__second ',
      reset($failure['selectors']),
    );
  }

  /**
   * A genuinely high-contrast palette produces no warnings at all.
   *
   * Without this the check could "pass" by reporting everything always,
   * which would be worse than useless: authors would learn to ignore it.
   *
   * The values matter. An earlier version of this test used white accents,
   * which correctly failed - the theme puts white text on the accent
   * colours, so white accents are white-on-white. Dark foreground colours
   * with light backing colours is what actually clears every pairing.
   */
  public function testHighContrastPaletteIsClean(): void {
    $failures = $this->analyzer->findFailures([
      'brand_primary' => '#000000',
      'brand_primary_dark' => '#000000',
      'brand_primary_accent' => '#000000',
      'brand_secondary' => '#ffffff',
      'brand_secondary_dark' => '#000000',
      'brand_secondary_accent' => '#ffffff',
    ]);

    $this->assertSame([], $failures, 'A black-on-white palette raises nothing.');
  }

  /**
   * Changing the offending colour clears the warning it caused.
   *
   * The complement of testCatchesTheIssue2178Pair(): the check has to
   * respond to what the author actually sets, not report a fixed list.
   */
  public function testFixingAColourClearsItsFailure(): void {
    $failing = $this->analyzer->findFailures([
      'brand_primary_dark' => '#107996',
      'brand_secondary' => '#e6ab49',
    ]);
    $this->assertNotNull($this->failureFor($failing, '--image-description-horizontal-text', '--brand-secondary'));

    $fixed = $this->analyzer->findFailures([
      'brand_primary_dark' => '#000000',
      'brand_secondary' => '#ffffff',
    ]);
    $this->assertNull(
      $this->failureFor($fixed, '--image-description-horizontal-text', '--brand-secondary'),
      'Darkening the text and lightening the panel clears that pair.',
    );
  }

  /**
   * Failures are ordered worst first.
   */
  public function testFailuresAreOrderedWorstFirst(): void {
    $failures = $this->analyzer->findFailures([
      'brand_primary' => '#e8e8e8',
      'brand_primary_dark' => '#dddddd',
      'brand_primary_accent' => '#eeeeee',
      'brand_secondary' => '#f0f0f0',
      'brand_secondary_dark' => '#e0e0e0',
      'brand_secondary_accent' => '#fafafa',
    ]);

    $this->assertGreaterThan(1, count($failures), 'A near-white palette fails in more than one place.');
    $ratios = array_column($failures, 'ratio');
    $sorted = $ratios;
    sort($sorted);
    $this->assertSame($sorted, $ratios, 'The worst pair is reported first.');
  }

  /**
   * Selector lists are not torn apart at commas inside parentheses.
   *
   * The theme uses `:is(#extra-specificity-hack, .horizontal-tabs)` widely.
   * Splitting that on every comma yields two fragments, neither of which is
   * a selector, and the fragments then prefix-match the wrong rules and
   * invent colour pairings that exist on no page. Reported selectors having
   * balanced parentheses is a cheap proxy for having been split correctly.
   */
  public function testSelectorListsAreNotTornApartAtNestedCommas(): void {
    $failures = $this->analyzer->findFailures([
      'brand_primary' => '#138aab',
      'brand_primary_dark' => '#107996',
      'brand_secondary' => '#e6ab49',
      'brand_secondary_accent' => '#f1b85a',
    ]);

    $this->assertNotEmpty($failures, 'This palette does fail somewhere, so there are selectors to check.');

    foreach ($failures as $failure) {
      foreach ($failure['selectors'] as $selector) {
        $this->assertSame(
          substr_count($selector, '('),
          substr_count($selector, ')'),
          sprintf('Selector "%s" has balanced parentheses.', $selector),
        );
      }
    }
  }

}
