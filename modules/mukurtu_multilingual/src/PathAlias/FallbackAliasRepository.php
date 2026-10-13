<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\PathAlias;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;

/**
 * Falls back to another language's alias when a path has none of its own.
 *
 * Pathauto stores an alias under the language of the translation it was
 * generated for, and core only matches aliases in the requested language or
 * 'und'. Content with no French translation therefore has no French alias,
 * so under /fr it showed as /fr/node/19 instead of
 * /fr/digital-heritage/tomorrow-first-dawn, and that second URL did not
 * resolve at all.
 *
 * This decorator asks the core repository first, and only for paths that
 * came back empty does it retry with the site default language, then every
 * other configured language. An alias in the requested language (or 'und')
 * always wins, so a real translated alias takes over as soon as one exists.
 *
 * Only the two methods AliasManager uses to build and resolve URLs
 * (preloadPathAlias() and lookupByAlias()) fall back. lookupBySystemPath()
 * is left exact; see the comment there.
 */
class FallbackAliasRepository implements AliasRepositoryInterface {

  public function __construct(
    protected AliasRepositoryInterface $inner,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function preloadPathAlias($preloaded, $langcode) {
    $aliases = $this->inner->preloadPathAlias($preloaded, $langcode);

    // An empty $preloaded means "load every alias", which already covers
    // anything a fallback could add.
    if (empty($preloaded)) {
      return $aliases;
    }

    $missing = array_values(array_diff($preloaded, array_keys($aliases)));
    foreach ($this->fallbackLangcodes($langcode) as $fallback) {
      if (!$missing) {
        break;
      }
      $found = $this->inner->preloadPathAlias($missing, $fallback);
      $aliases += $found;
      $missing = array_values(array_diff($missing, array_keys($found)));
    }

    return $aliases;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupBySystemPath($path, $langcode) {
    // Deliberately no fallback. Core's path field (PathFieldItemList) and
    // pathauto (AliasStorageHelper::loadBySource()) use this to find the
    // alias that belongs to one specific translation. Falling back here would
    // hand a French translation the English alias entity, and saving the
    // translation would then edit or re-language that English alias.
    return $this->inner->lookupBySystemPath($path, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function lookupByAlias($alias, $langcode) {
    $path = $this->inner->lookupByAlias($alias, $langcode);
    foreach ($this->fallbackLangcodes($langcode) as $fallback) {
      if ($path) {
        break;
      }
      $path = $this->inner->lookupByAlias($alias, $fallback);
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function pathHasMatchingAlias($initial_substring) {
    return $this->inner->pathHasMatchingAlias($initial_substring);
  }

  /**
   * Returns the languages to retry, site default first.
   *
   * @param string|null $langcode
   *   The language that was originally requested.
   *
   * @return string[]
   *   Language codes, excluding the requested one and 'und' (which the core
   *   repository has already checked).
   */
  protected function fallbackLangcodes(?string $langcode): array {
    if ($langcode === NULL || $langcode === LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      return [];
    }

    $langcodes = array_keys($this->languageManager->getLanguages());
    $default = $this->languageManager->getDefaultLanguage()->getId();
    $ordered = array_unique(array_merge([$default], $langcodes));

    return array_values(array_filter($ordered, fn (string $code): bool => $code !== $langcode && $code !== LanguageInterface::LANGCODE_NOT_SPECIFIED));
  }

}
