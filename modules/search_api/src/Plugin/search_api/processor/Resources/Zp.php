<?php

namespace Drupal\search_api\Plugin\search_api\processor\Resources;

/**
 * Represents characters of the Unicode category "Zp" ("Separator, Paragraph").
 */
class Zp implements UnicodeCharacterPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    // phpcs:disable
    // cspell:disable
    return
      '\x{2029}';
    // phpcs:enable
    // cspell:enable
  }

}
