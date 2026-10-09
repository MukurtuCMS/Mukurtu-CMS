<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\language\ConfigurableLanguageInterface;

/**
 * Reindexes language fallback data when the site's languages change.
 *
 * Search API's language_with_fallback processor works out which languages
 * fall back to an item when the item is indexed, not when it is searched. An
 * item indexed before French was added never gets 'fr', so the browse views'
 * language_with_fallback filter hides it on /fr until it is reindexed. Weight
 * sets the fallback order, so reordering languages has the same effect.
 */
class LanguageFallbackReindexHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for configurable_language.
   */
  #[Hook('configurable_language_insert')]
  public function languageInsert(ConfigurableLanguageInterface $language): void {
    $this->reindexFallbackIndexes();
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for configurable_language.
   */
  #[Hook('configurable_language_update')]
  public function languageUpdate(ConfigurableLanguageInterface $language): void {
    $original = method_exists($language, 'getOriginal') ? $language->getOriginal() : ($language->original ?? NULL);
    if (!$original || $original->getWeight() !== $language->getWeight()) {
      $this->reindexFallbackIndexes();
    }
  }

  /**
   * Queues every enabled index with a language_with_fallback field.
   */
  protected function reindexFallbackIndexes(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      if (!$index->status() || $index->isReadOnly()) {
        continue;
      }
      foreach ($index->getFields() as $field) {
        if ($field->getPropertyPath() === 'language_with_fallback' && $field->getDatasourceId() === NULL) {
          $index->reindex();
          break;
        }
      }
    }
  }

}
