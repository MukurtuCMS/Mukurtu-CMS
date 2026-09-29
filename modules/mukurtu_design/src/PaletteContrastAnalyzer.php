<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\Core\Extension\ExtensionList;
use Drupal\mukurtu_core\Color\ContrastCalculator;

/**
 * Checks a custom palette's colours against the theme's real colour pairings.
 *
 * The Custom palette lets an author set six colours that are written
 * straight into :root by DesignPalette::generateCustomCss(). Nothing
 * validated them, so a site could ship text that fails WCAG 1.4.3
 * everywhere with no indication anything was wrong. That is an ATAG 2.0
 * B.2.2 gap: the tool asked authors to guarantee a contrast it gave them no
 * way to check. See issue #1054.
 *
 * The pairs are derived from the compiled stylesheet rather than
 * hand-listed, because a hand-maintained list goes stale the first time
 * someone adds a component and nothing fails to tell you.
 */
class PaletteContrastAnalyzer {

  /**
   * Compiled stylesheet, relative to the theme root.
   */
  protected const STYLESHEET = 'css/style.css';

  /**
   * Theme whose stylesheet defines the pairings.
   */
  protected const THEME = 'mukurtu_v4';

  /**
   * Font size at or above which WCAG's large-text threshold applies.
   *
   * WCAG's "large scale" is 18pt / 24px, or 14pt / 18.66px when bold. Only
   * the size is checked here; a bold rule below 24px is measured against
   * the stricter normal-text threshold, so this errs toward warning rather
   * than toward silence.
   */
  protected const LARGE_TEXT_PX = 24.0;

  public function __construct(protected ExtensionList $themeExtensionList) {}

  /**
   * Finds colour pairings that fail WCAG 1.4.3 under the given palette.
   *
   * @param array $colors
   *   Custom palette colours keyed as DesignPalette::CSS_VAR_MAPPING keys.
   *
   * @return array[]
   *   One entry per failing pair, each with 'foreground' and 'background'
   *   (CSS custom property names), 'foreground_value' and
   *   'background_value' (resolved hex), 'ratio', 'required' and
   *   'selectors' (up to three example selectors). Empty when the palette
   *   passes, or when the stylesheet cannot be read.
   */
  public function findFailures(array $colors): array {
    $css = $this->loadStylesheet();
    if ($css === NULL) {
      return [];
    }

    [$tokens, $editable] = $this->tokenValues($css, $colors);
    [$backgrounds, $foregrounds, $fontSizes] = $this->declarations($css);

    $failures = [];
    foreach ($foregrounds as [$selector, $fgRaw, $fontSize]) {
      $bgRaw = $backgrounds[$selector] ?? $this->nearestAncestorValue($selector, $backgrounds);
      if ($bgRaw === NULL) {
        continue;
      }

      $fgEditable = FALSE;
      $bgEditable = FALSE;
      $fg = $this->resolve($fgRaw, $tokens, $editable, $fgEditable);
      $bg = $this->resolve($bgRaw, $tokens, $editable, $bgEditable);

      // Only report what the author can actually act on. A pair between two
      // fixed colours failing is a theme bug to file, not something to put
      // in front of someone choosing brand colours.
      if (!$fgEditable && !$bgEditable) {
        continue;
      }

      $ratio = ($fg === NULL || $bg === NULL) ? NULL : ContrastCalculator::ratio($fg, $bg);
      if ($ratio === NULL) {
        continue;
      }

      // font-size is inherited, and is very often set on the element
      // carrying the background rather than on the one carrying the text -
      // .collections__content__container h2 sets the size, h2 a sets the
      // colour. Reading only the colour's own block reports a 4.5:1
      // requirement for text that is large enough to need 3:1, which would
      // push an author to change a colour that was already fine.
      $fontSize ??= $this->nearestAncestorValue($selector, $fontSizes);

      $required = $this->isLargeText($fontSize, $tokens)
        ? ContrastCalculator::AA_LARGE_TEXT
        : ContrastCalculator::AA_NORMAL_TEXT;

      if ($ratio >= $required) {
        continue;
      }

      // Key on the token pair, not the selector: one failing pair usually
      // appears in many rules, and an author needs the colour to change,
      // not a list of every place it shows up.
      $key = $this->tokenName($fgRaw) . '|' . $this->tokenName($bgRaw);
      if (isset($failures[$key])) {
        if (count($failures[$key]['selectors']) < 3) {
          $failures[$key]['selectors'][] = $selector;
        }
        continue;
      }

      $failures[$key] = [
        'foreground' => $this->tokenName($fgRaw),
        'background' => $this->tokenName($bgRaw),
        'foreground_value' => $fg,
        'background_value' => $bg,
        'ratio' => $ratio,
        'required' => $required,
        'selectors' => [$selector],
      ];
    }

    usort($failures, fn($a, $b) => $a['ratio'] <=> $b['ratio']);
    return $failures;
  }

  /**
   * Reads the compiled stylesheet, comments stripped.
   */
  protected function loadStylesheet(): ?string {
    try {
      $path = $this->themeExtensionList->getPath(static::THEME) . '/' . static::STYLESHEET;
    }
    catch (\Throwable) {
      return NULL;
    }

    if (!is_readable($path)) {
      return NULL;
    }

    $css = file_get_contents($path);
    return $css === FALSE ? NULL : preg_replace('#/\*.*?\*/#s', '', $css);
  }

  /**
   * Builds the custom property table: theme defaults, author values on top.
   *
   * The generated custom palette CSS only ever sets the six editable
   * properties, so every other token keeps whatever :root gives it - which
   * is how a custom palette can inherit a derived colour like
   * --image-description-horizontal-text that is defined in terms of a
   * colour the author just changed.
   */
  protected function tokenValues(string $css, array $colors): array {
    $tokens = [];
    $editable = [];

    // Every :root block, not just the first. The compiled stylesheet has
    // seven of them - colours in one, font sizes in another, and so on -
    // and reading only the first leaves most tokens unresolvable, which
    // silently drops the pairs that depend on them instead of reporting
    // them. Later declarations win, as they do in the cascade.
    preg_match_all('/:root\s*\{([^}]*)\}/', $css, $roots, PREG_SET_ORDER);
    foreach ($roots as $root) {
      preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $root[1], $found, PREG_SET_ORDER);
      foreach ($found as $declaration) {
        $tokens[$declaration[1]] = trim($declaration[2]);
      }
    }

    foreach (DesignPalette::CSS_VAR_MAPPING as $key => $property) {
      if (!empty($colors[$key])) {
        $tokens[$property] = trim($colors[$key]);
      }
      // Tracked whether or not a value was supplied, so findFailures() can
      // ignore pairs the author has no control over.
      $editable[$property] = TRUE;
    }

    return [$tokens, $editable];
  }

  /**
   * Splits the stylesheet into background and colour declarations.
   *
   * @return array
   *   [$backgrounds, $foregrounds, $fontSizes]: a selector => value map, a
   *   list of [$selector, $value, $fontSize], and a second selector =>
   *   value map for font sizes.
   */
  protected function declarations(string $css): array {
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);

    $backgrounds = [];
    $foregrounds = [];
    $fontSizes = [];

    foreach ($blocks as [, $selectorList, $body]) {
      // At-rule preludes (@media, @supports) are not selectors.
      if (str_contains($selectorList, '@')) {
        continue;
      }

      preg_match('/background(?:-color)?\s*:\s*([^;!]+)/', $body, $bg);
      // The lookbehind keeps this off border-color, outline-color and the
      // rest, which would otherwise be read as text colours.
      preg_match('/(?<![-\w])color\s*:\s*([^;!]+)/', $body, $fg);
      preg_match('/font-size\s*:\s*([^;!]+)/', $body, $size);

      // A font-size-only rule still matters: it is very often the ancestor
      // that establishes whether the text below it is large enough for the
      // 3:1 threshold, and skipping those here quietly reported every such
      // pair against the stricter 4.5:1.
      if (!$bg && !$fg && !$size) {
        continue;
      }

      foreach ($this->splitSelectors($selectorList) as $selector) {
        if ($bg) {
          $backgrounds[$selector] = trim($bg[1]);
        }
        if ($fg) {
          $foregrounds[] = [$selector, trim($fg[1]), $size ? trim($size[1]) : NULL];
        }
        if ($size) {
          $fontSizes[$selector] = trim($size[1]);
        }
      }
    }

    return [$backgrounds, $foregrounds, $fontSizes];
  }

  /**
   * Splits a selector list on commas that are not inside parentheses.
   *
   * A plain explode(',') tears `:is(#extra-specificity-hack, .horizontal-tabs)`
   * into two fragments, neither of which is a selector. The theme uses that
   * specificity hack widely, so the damage is not theoretical: the
   * fragments then match nothing, or worse, prefix-match the wrong rule and
   * invent a colour pairing that does not exist on any page.
   *
   * @param string $selectorList
   *   The raw selector list from a rule.
   *
   * @return string[]
   *   The individual selectors, whitespace normalised.
   */
  protected function splitSelectors(string $selectorList): array {
    $selectors = [];
    $current = '';
    $depth = 0;

    foreach (str_split($selectorList) as $character) {
      if ($character === '(') {
        $depth++;
      }
      elseif ($character === ')') {
        $depth = max(0, $depth - 1);
      }

      if ($character === ',' && $depth === 0) {
        $selectors[] = $current;
        $current = '';
        continue;
      }

      $current .= $character;
    }
    $selectors[] = $current;

    $normalised = [];
    foreach ($selectors as $selector) {
      $selector = trim(preg_replace('/\s+/', ' ', $selector));
      if ($selector !== '') {
        $normalised[] = $selector;
      }
    }

    return $normalised;
  }

  /**
   * Finds the value a selector inherits from its nearest styled ancestor.
   *
   * Used for both backgrounds and font sizes, because text very often gets
   * its colour on a descendant of the element carrying the background or
   * the size. That is exactly the shape of issue #2178, where the panel set
   * background-color on one selector and the heading colour on another;
   * matching only within a single declaration block would miss precisely
   * the defect this check exists to prevent.
   *
   * This is a prefix match, not a DOM: it finds the longest selector in the
   * map that this selector begins with, which stands in for "nearest
   * ancestor". It therefore cannot see values applied via siblings, via
   * classes added by JavaScript, or through a different branch of the tree,
   * and those are reported as unknown rather than guessed at.
   *
   * @param string $selector
   *   The selector to resolve for.
   * @param array $candidates
   *   Selector => value map to search.
   *
   * @return string|null
   *   The nearest ancestor's value, or NULL if no ancestor sets one.
   */
  protected function nearestAncestorValue(string $selector, array $candidates): ?string {
    $best = NULL;
    $bestLength = 0;

    foreach ($candidates as $candidate => $value) {
      if (str_starts_with($selector, $candidate . ' ') && strlen($candidate) > $bestLength) {
        $best = $value;
        $bestLength = strlen($candidate);
      }
    }

    return $best;
  }

  /**
   * Resolves a CSS value to a hex colour, following var() chains.
   *
   * @param string $value
   *   The declared value.
   * @param array $tokens
   *   The custom property table.
   * @param array $editable
   *   Custom properties the author can set, as a set keyed by name.
   * @param bool $touchesEditable
   *   Set to TRUE if resolution passed through a property the author can
   *   edit, by reference.
   * @param int $depth
   *   Recursion guard against a token defined in terms of itself.
   */
  protected function resolve(string $value, array $tokens, array $editable, bool &$touchesEditable, int $depth = 0): ?string {
    $value = trim($value);

    if ($depth > 10) {
      return NULL;
    }

    if (preg_match('/^var\(\s*(--[\w-]+)\s*(?:,\s*(.+))?\)$/', $value, $match)) {
      $property = $match[1];
      if (isset($editable[$property])) {
        $touchesEditable = TRUE;
      }

      if (isset($tokens[$property])) {
        return $this->resolve($tokens[$property], $tokens, $editable, $touchesEditable, $depth + 1);
      }

      // Fall back to the var()'s own default, if it declared one.
      return isset($match[2]) ? $this->resolve($match[2], $tokens, $editable, $touchesEditable, $depth + 1) : NULL;
    }

    return ContrastCalculator::parse($value) === NULL ? NULL : $value;
  }

  /**
   * Whether a font-size declaration reaches WCAG's large-text threshold.
   */
  protected function isLargeText(?string $fontSize, array $tokens): bool {
    if ($fontSize === NULL) {
      return FALSE;
    }

    if (preg_match('/^var\(\s*(--[\w-]+)/', $fontSize, $match)) {
      $fontSize = $tokens[$match[1]] ?? '';
    }

    if (preg_match('/^([\d.]+)(px|rem)$/', trim($fontSize), $match)) {
      // rem against the browser default; the theme does not change the root
      // font size. em is deliberately not handled: it is relative to the
      // parent's computed size, which is not knowable from the stylesheet
      // alone, and guessing would produce a confident wrong threshold.
      $size = (float) $match[1];
      return ($match[2] === 'px' ? $size : $size * 16) >= static::LARGE_TEXT_PX;
    }

    return FALSE;
  }

  /**
   * Names a value for display: the custom property if it is one.
   */
  protected function tokenName(string $value): string {
    return preg_match('/^var\(\s*(--[\w-]+)/', trim($value), $match) ? $match[1] : trim($value);
  }

}
