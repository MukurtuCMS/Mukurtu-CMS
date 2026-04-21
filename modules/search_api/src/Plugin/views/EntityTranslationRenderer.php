<?php

namespace Drupal\search_api\Plugin\views;

use Drupal\Core\Language\LanguageInterface;
use Drupal\views\Entity\Render\TranslationLanguageRenderer;
use Drupal\views\ResultRow as ViewsResultRow;

/**
 * Renders entity translations in their row language.
 */
class EntityTranslationRenderer extends TranslationLanguageRenderer {

  /**
   * {@inheritdoc}
   */
  public function getLangcode(ViewsResultRow $row) {
    // If our normal query plugin is used, the fallback shouldn't really ever be
    // needed, but if it is we fall back to the current request's content
    // language.
    return $row->search_api_language ?? $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
  }

}
