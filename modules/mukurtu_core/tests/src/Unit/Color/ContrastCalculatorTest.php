<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit\Color;

use Drupal\mukurtu_core\Color\ContrastCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the WCAG contrast ratio maths.
 */
#[CoversClass(ContrastCalculator::class)]
#[Group('mukurtu_core')]
class ContrastCalculatorTest extends UnitTestCase {

  /**
   * The extremes, which pin both ends of the scale.
   */
  public function testExtremes(): void {
    $this->assertEqualsWithDelta(21.0, ContrastCalculator::ratio('#ffffff', '#000000'), 0.01);
    $this->assertEqualsWithDelta(1.0, ContrastCalculator::ratio('#777777', '#777777'), 0.01);
  }

  /**
   * Order does not change the ratio.
   */
  public function testRatioIsSymmetric(): void {
    $this->assertSame(
      ContrastCalculator::ratio('#107996', '#e6ab49'),
      ContrastCalculator::ratio('#e6ab49', '#107996'),
    );
  }

  /**
   * Real Mukurtu colour pairs, measured independently of this code.
   *
   * These are the values recorded in the findings for the September 2026
   * remediation cycle, so a change to the maths that quietly shifted them
   * would show up here rather than in a conformance claim.
   */
  #[DataProvider('mukurtuColorPairs')]
  public function testKnownMukurtuPairs(string $fg, string $bg, float $expected, string $why): void {
    $this->assertEqualsWithDelta($expected, ContrastCalculator::ratio($fg, $bg), 0.01, $why);
  }

  /**
   * Data provider for testKnownMukurtuPairs().
   */
  public static function mukurtuColorPairs(): array {
    return [
      'issue #2178, before the fix' => ['#107996', '#e6ab49', 2.45, 'Brand primary dark on Blue and gold secondary, the failing pair.'],
      'issue #2178, after the fix' => ['#0d4a5c', '#e6ab49', 4.78, 'The per-palette override that resolved it.'],
      'red-bone hero stroke' => ['#9a1134', '#ffffff', 8.39, 'Brand primary dark against the white glyph stroke.'],
      'blue-gold hero stroke' => ['#107996', '#ffffff', 5.00, 'Same, for the other built-in palette.'],
    ];
  }

  /**
   * Shorthand hex expands by repeating digits, not by zero-padding.
   *
   * #fff is white; read as #0f0f0f it would be nearly black, and every
   * ratio computed against it would be wrong in the safe-looking direction.
   */
  public function testShorthandHex(): void {
    $this->assertSame([255, 255, 255], ContrastCalculator::parse('#fff'));
    $this->assertSame([255, 255, 255], ContrastCalculator::parse('fff'));
    $this->assertEqualsWithDelta(
      ContrastCalculator::ratio('#fff', '#000'),
      ContrastCalculator::ratio('#ffffff', '#000000'),
      0.0001,
    );
  }

  /**
   * Unparseable colours return NULL rather than a confident wrong number.
   */
  #[DataProvider('unparseableColors')]
  public function testUnparseableColors(string $value): void {
    $this->assertNull(ContrastCalculator::parse($value));
    $this->assertNull(ContrastCalculator::ratio($value, '#ffffff'));
  }

  /**
   * Data provider for testUnparseableColors().
   */
  public static function unparseableColors(): array {
    return [
      'named colour' => ['rebeccapurple'],
      'keyword' => ['currentcolor'],
      'functional notation' => ['rgb(16, 121, 150)'],
      'gradient' => ['linear-gradient(#fff, #000)'],
      'transparent' => ['transparent'],
      'empty' => [''],
      'wrong length' => ['#ffff'],
      'not hex' => ['#gggggg'],
    ];
  }

  /**
   * Luminance is linearised before weighting.
   *
   * Skipping the sRGB transfer function is the classic way to get this
   * subtly wrong, and it shows up as mid-greys being too bright. Pure grey
   * #777 has a known luminance well below the 0.467 a naive average gives.
   */
  public function testLuminanceIsLinearised(): void {
    $this->assertEqualsWithDelta(0.0, ContrastCalculator::relativeLuminance([0, 0, 0]), 0.0001);
    $this->assertEqualsWithDelta(1.0, ContrastCalculator::relativeLuminance([255, 255, 255]), 0.0001);
    $this->assertEqualsWithDelta(0.1845, ContrastCalculator::relativeLuminance([119, 119, 119]), 0.001);
  }

}
