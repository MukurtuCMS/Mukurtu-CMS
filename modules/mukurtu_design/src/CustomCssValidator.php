<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

/**
 * Checks custom CSS for the few constructs that can do harm.
 *
 * The CSS is served as its own file, so it cannot run script. What it can
 * still do is break out of a <style> element if it is ever inlined, and make
 * every visitor's browser request files from another host, which leaks their
 * IP address to a third party and, with attribute selectors, page data too.
 * This rejects those. It does not try to judge whether the CSS is any good.
 *
 * Checks run on the CSS as a browser would read it: escapes such as
 * "@\69mport" are decoded first, and backslashes count as slashes because
 * browsers treat "https:\\host" as "https://host". Comments are deliberately
 * not stripped, since telling a comment from a string reliably takes a full
 * tokenizer; a URL inside a comment is rejected too, which is the safe side.
 */
final class CustomCssValidator {

  /**
   * The CSS contains "<", which could close a <style> element.
   */
  public const MARKUP = 'markup';

  /**
   * The CSS uses @import.
   */
  public const IMPORT = 'import';

  /**
   * The CSS refers to a file on another host.
   */
  public const EXTERNAL = 'external';

  /**
   * SVG namespace declarations, which name a URL but never fetch it.
   *
   * Common in data: URIs that inline an SVG. The quote may be literal or
   * percent-encoded.
   */
  private const SVG_NAMESPACE = '~xmlns(?::xlink)?=(?:[\'"]|%2[27])https?://www\.w3\.org/(?:2000/svg|1999/xlink)(?:[\'"]|%2[27])~i';

  /**
   * Finds the problems in a piece of custom CSS.
   *
   * @param string $css
   *   The CSS as the author entered it.
   *
   * @return string[]
   *   The problem codes found, using this class's constants. Empty when the
   *   CSS is acceptable.
   */
  public function validate(string $css): array {
    $decoded = self::decode($css);
    $problems = [];

    if (str_contains($decoded, '<')) {
      $problems[] = self::MARKUP;
    }
    if (stripos($decoded, '@import') !== FALSE) {
      $problems[] = self::IMPORT;
    }

    // URL parsers drop tabs and newlines anywhere in a URL, so
    // "ht<tab>tps://" still reaches another host.
    $url_text = str_replace(["\t", "\n", "\r", "\f", '\\'], ['', '', '', '', '/'], $decoded);
    $url_text = preg_replace(self::SVG_NAMESPACE, '', $url_text);
    // "http:host" with no slashes still reaches another host when the page
    // itself is on a different scheme.
    if (str_contains($url_text, '//') || preg_match('~https?:~i', $url_text)) {
      $problems[] = self::EXTERNAL;
    }

    return $problems;
  }

  /**
   * Decodes CSS escape sequences.
   *
   * A backslash followed by one to six hex digits (and one optional space)
   * is that code point. A backslash followed by any other character is that
   * character.
   */
  private static function decode(string $css): string {
    return preg_replace_callback(
      // No /u flag: on invalid UTF-8 a /u pattern fails outright, and the
      // checks would then run on the undecoded text. Byte-wise, a backslash
      // before a multibyte character just passes its bytes through.
      '/\\\\(?:([0-9a-fA-F]{1,6})[ \t\n\r\f]?|(.))/s',
      static function (array $match): string {
        if (isset($match[2]) && $match[1] === '') {
          return $match[2];
        }
        $code_point = hexdec($match[1]);
        // Invalid code points become U+FFFD, as in a browser.
        if ($code_point === 0 || $code_point > 0x10FFFF || ($code_point >= 0xD800 && $code_point <= 0xDFFF)) {
          return "\u{FFFD}";
        }
        return mb_chr($code_point, 'UTF-8');
      },
      $css
    ) ?? $css;
  }

}
