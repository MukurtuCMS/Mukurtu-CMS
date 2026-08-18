<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate;

/**
 * Provides an interface for queuing Search API indexing after a migration.
 */
interface SearchApiIndexerInterface {

  /**
   * Queues a Search API indexing batch for every enabled, indexable index.
   *
   * @return int
   *   The number of indexes queued for indexing.
   */
  public function queueIndexingForAllIndexes(): int;

}
