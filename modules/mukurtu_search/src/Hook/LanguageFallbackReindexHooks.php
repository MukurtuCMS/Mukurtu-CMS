<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\IndexingBatchHelperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reindexes language fallback data when the site's languages change.
 *
 * Search API's language_with_fallback processor works out which languages
 * fall back to an item when the item is indexed, not when it is searched. An
 * item indexed before French was added never gets 'fr', so the browse views'
 * language_with_fallback filter hides it on /fr until it is reindexed. Weight
 * sets the fallback order, so reordering languages has the same effect.
 *
 * Queuing alone isn't enough: cron indexes only cron_limit items per run, so
 * older content could stay hidden for hours. The index is batched right away
 * as well, which the language add and list forms process on submit. Anywhere
 * no batch is processed (Drush, config import), the queue falls to cron.
 */
class LanguageFallbackReindexHooks {

  /**
   * IDs of the indexes already batched in this request.
   *
   * The language list form saves every language when reordering, so the
   * hooks can fire several times in one request.
   *
   * @var string[]
   */
  protected array $batched = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'search_api.indexing_batch_helper')]
    protected IndexingBatchHelperInterface $indexingBatchHelper,
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
   * Queues and batches every enabled index with a language_with_fallback field.
   */
  protected function reindexFallbackIndexes(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      if (!$index->status() || $index->isReadOnly() || !$this->hasFallbackField($index)) {
        continue;
      }
      $index->reindex();
      // Languages created during site install come before any content. An
      // empty index would make Search API's batch report "Couldn't index
      // items", so skip those too.
      if (InstallerKernel::installationAttempted() || in_array($index->id(), $this->batched, TRUE) || !$index->getTrackerInstance()->getRemainingItemsCount()) {
        continue;
      }
      try {
        $this->indexingBatchHelper->createBatch($index);
        $this->batched[] = $index->id();
      }
      catch (SearchApiException) {
        // Another process holds the indexing lock. The items stay queued.
      }
    }
  }

  /**
   * Whether the index has a language_with_fallback field.
   */
  protected function hasFallbackField($index): bool {
    foreach ($index->getFields() as $field) {
      if ($field->getPropertyPath() === 'language_with_fallback' && $field->getDatasourceId() === NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
