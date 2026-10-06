<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\Plugin\search_api\processor;

use Drupal\search_api\Plugin\search_api\processor\Transliteration;

/**
 * Transliterates fulltext without losing characters that have no mapping.
 *
 * Drupal's transliteration replaces every character it has no mapping for
 * with "?", which the tokenizer then drops, so words written in Osage,
 * Tifinagh, Adlam, N'Ko, and other scripts were never indexed. For fulltext
 * values and search keys this keeps such characters unchanged instead, so a
 * search typed in the same script matches.
 *
 * Cherokee needs one more step: ignorecase runs first and turns it into the
 * Cherokee lowercase letters, which have no mapping, while the uppercase ones
 * do. So an unmapped character is retried in uppercase before being kept.
 *
 * Other values, such as string fields used for facets, keep the core
 * behavior. The database backend compares them with utf8mb4_general_ci,
 * which treats all 4-byte characters (Osage, Adlam, emoji) as equal, so
 * different values would be merged. Fulltext words are compared exactly.
 *
 * Replaces the class of Search API's "transliteration" processor, so it is
 * not discovered as a plugin of its own.
 *
 * @see \Drupal\mukurtu_search\EventSubscriber\GatheringProcessorsSubscriber
 */
class KeepUnmappedTransliteration extends Transliteration {

  /**
   * Stands in for an unmapped character; a Unicode noncharacter.
   */
  protected const UNMAPPED = "\u{FDD0}";

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, $type) {
    if ($this->shouldProcess($value) && $this->getDataTypeHelper()->isTextType($type)) {
      $value = $this->transliterateKeepingUnmapped($value);
      return;
    }
    parent::processFieldValue($value, $type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processKey(&$value) {
    if ($this->shouldProcess($value)) {
      $value = $this->transliterateKeepingUnmapped($value);
    }
  }

  /**
   * Transliterates a string, keeping characters that have no mapping.
   *
   * @param string $value
   *   The string to transliterate.
   *
   * @return string
   *   The transliterated string.
   */
  protected function transliterateKeepingUnmapped(string $value): string {
    $transliterator = $this->getTransliterator();
    $langcode = $this->getLangcode();
    $result = $transliterator->transliterate($value, $langcode, static::UNMAPPED);
    if (!str_contains($result, static::UNMAPPED)) {
      return $result;
    }

    // Transliteration maps one character at a time, so redoing it per
    // character gives the same result for the characters that do map.
    $result = '';
    foreach (mb_str_split($value) as $character) {
      $mapped = $transliterator->transliterate($character, $langcode, static::UNMAPPED);
      if ($mapped === static::UNMAPPED) {
        $upper = mb_strtoupper($character);
        $mapped = $upper === $character ? static::UNMAPPED : $transliterator->transliterate($upper, $langcode, static::UNMAPPED);
        $mapped = $mapped === static::UNMAPPED ? $character : mb_strtolower($mapped);
      }
      $result .= $mapped;
    }
    return $result;
  }

}
