<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;

/**
 * Reads the collection display order and keeps the search indexes in step.
 *
 * The /collections views sort on an indexed weight, not on this config
 * directly, so the order holds across pages. Any change to the order has to
 * be followed by reindex().
 */
class CollectionOrder {

  /**
   * The order config object name.
   */
  public const CONFIG_NAME = 'mukurtu_collection.collection_order';

  /**
   * Sort mode: order by title.
   */
  public const MODE_ALPHABETICAL = 'alphabetical';

  /**
   * Sort mode: use the saved order.
   */
  public const MODE_CUSTOM = 'custom';

  /**
   * The weight for collections that aren't in the saved order.
   *
   * They sort after every placed collection, then by title.
   */
  public const UNPLACED_WEIGHT = 1000000;

  /**
   * The name of the indexed weight field.
   */
  public const FIELD_NAME = 'collection_display_weight';

  /**
   * The indexes that /collections can be built from.
   */
  public const INDEX_IDS = [
    'mukurtu_collection_index',
    'mukurtu_default_solr_index',
  ];

  /**
   * Constructs a CollectionOrder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Gets the current sort mode.
   */
  public function getSortMode(): string {
    return $this->configFactory->get(static::CONFIG_NAME)->get('sort_mode') === static::MODE_CUSTOM
      ? static::MODE_CUSTOM
      : static::MODE_ALPHABETICAL;
  }

  /**
   * Gets the saved order.
   *
   * @return int[]
   *   Collection IDs, first to last.
   */
  public function getOrder(): array {
    return array_values(array_map('intval', $this->configFactory->get(static::CONFIG_NAME)->get('order') ?? []));
  }

  /**
   * Gets the weight to index for a collection.
   *
   * @param int $nid
   *   The collection node ID.
   *
   * @return int
   *   0 for every collection in alphabetical mode, so the title sort decides.
   *   In custom mode, the collection's position in the saved order, or
   *   UNPLACED_WEIGHT if it isn't there.
   */
  public function getWeight(int $nid): int {
    if ($this->getSortMode() !== static::MODE_CUSTOM) {
      return 0;
    }
    $position = array_search($nid, $this->getOrder(), TRUE);
    return $position === FALSE ? static::UNPLACED_WEIGHT : $position;
  }

  /**
   * Saves the sort mode and order, then reindexes if either changed.
   *
   * @param string $sort_mode
   *   One of the MODE_* constants.
   * @param int[] $order
   *   Collection IDs, first to last.
   */
  public function save(string $sort_mode, array $order): void {
    $order = array_values(array_map('intval', $order));
    $config = $this->configFactory->getEditable(static::CONFIG_NAME);
    $changed = $config->get('sort_mode') !== $sort_mode || $this->getOrder() !== $order;
    $config
      ->set('sort_mode', $sort_mode)
      ->set('order', $order)
      ->save();
    if ($changed) {
      $this->reindex();
    }
  }

  /**
   * Marks every collection for reindexing in each index with the weight.
   *
   * On an index set to index directly, Search API indexes them at the end of
   * the current request, so the new order shows on the next page load.
   * Otherwise they wait for cron.
   */
  public function reindex(): void {
    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }
    $indexes = array_filter(
      $this->entityTypeManager->getStorage('search_api_index')->loadMultiple(static::INDEX_IDS),
      fn (IndexInterface $index): bool => $index->status()
        && $index->isValidDatasource('entity:node')
        && $index->getField(static::FIELD_NAME) !== NULL,
    );
    if (!$indexes) {
      return;
    }

    $item_ids = $this->getCollectionItemIds();
    if (!$item_ids) {
      return;
    }

    foreach ($indexes as $index) {
      $index->trackItemsUpdated('entity:node', $item_ids);
    }
  }

  /**
   * Gets datasource item IDs for every translation of every collection.
   *
   * @return string[]
   *   Item IDs in the "{nid}:{langcode}" form the node datasource uses.
   */
  protected function getCollectionItemIds(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'collection')
      ->accessCheck(FALSE)
      ->execute();

    $item_ids = [];
    foreach (array_chunk($nids, 50) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $node) {
        foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
          $item_ids[] = $node->id() . ':' . $langcode;
        }
      }
      $storage->resetCache($chunk);
    }
    return $item_ids;
  }

}
