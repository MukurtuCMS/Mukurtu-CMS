<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Color;

/**
 * WCAG contrast ratio maths.
 *
 * Kept separate from anything that reads CSS or forms so it can be tested
 * against the published worked examples, and reused: the accessibility
 * program keeps needing this (see docs/accessibility/).
 *
 * Implements the relative luminance and contrast ratio definitions from
 * WCAG 2.1, https://www.w3.org/TR/WCAG21/#dfn-relative-luminance and
 * #dfn-contrast-ratio.
 */
final class ContrastCalculator {

  /**
   * Minimum ratio for normal-size text at Level AA (WCAG 1.4.3).
   */
  public const AA_NORMAL_TEXT = 4.5;

  /**
   * Minimum ratio for large text, and for non-text contrast (1.4.3, 1.4.11).
   */
  public const AA_LARGE_TEXT = 3.0;

  /**
   * Parses a CSS hex colour into 8-bit RGB components.
   *
   * Handles #rgb and #rrggbb, with or without the leading hash. Anything
   * else - a named colour, rgb(), a gradient, currentcolor - returns NULL,
   * because guessing at those would produce confident wrong numbers.
   *
   * @param string $color
   *   The colour to parse.
   *
   * @return int[]|null
   *   [$r, $g, $b] with each component 0-255, or NULL if unparseable.
   */
  public static function parse(string $color): ?array {
    $hex = ltrim(trim($color), '#');

    if (strlen($hex) === 3 && ctype_xdigit($hex)) {
      // #abc is shorthand for #aabbcc, not for #0a0b0c.
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
      return NULL;
    }

    return [
      (int) hexdec(substr($hex, 0, 2)),
      (int) hexdec(substr($hex, 2, 2)),
      (int) hexdec(substr($hex, 4, 2)),
    ];
  }

  /**
   * Relative luminance of an sRGB colour, 0 (black) to 1 (white).
   *
   * @param int[] $rgb
   *   [$r, $g, $b], each 0-255.
   *
   * @return float
   *   The relative luminance.
   */
  public static function relativeLuminance(array $rgb): float {
    $channels = [];
    foreach ($rgb as $value) {
      $c = $value / 255;
      // Undo the sRGB transfer function before weighting. Skipping this
      // linearisation is the classic way to get contrast maths subtly and
      // consistently wrong.
      $channels[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
  }

  /**
   * Contrast ratio between two colours, from 1.0 to 21.0.
   *
   * Order does not matter; the lighter colour is always the numerator.
   *
   * @param string $foreground
   *   A hex colour.
   * @param string $background
   *   A hex colour.
   *
   * @return float|null
   *   The ratio, or NULL if either colour could not be parsed.
   */
  public static function ratio(string $foreground, string $background): ?float {
    $fg = self::parse($foreground);
    $bg = self::parse($background);

    if ($fg === NULL || $bg === NULL) {
      return NULL;
    }

    $l1 = self::relativeLuminance($fg);
    $l2 = self::relativeLuminance($bg);

    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);

    return ($lighter + 0.05) / ($darker + 0.05);
  }

}
