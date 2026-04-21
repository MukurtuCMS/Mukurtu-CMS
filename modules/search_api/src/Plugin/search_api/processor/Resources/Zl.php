<?php

namespace Drupal\search_api\Plugin\search_api\processor\Resources;

/**
 * Represents characters of the Unicode category "Zl" ("Separator, Line").
 */
class Zl implements UnicodeCharacterPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    // phpcs:disable
    // cspell:disable
    return
      '\x{2028}';
    // phpcs:enable
    // cspell:enable
  }

}
