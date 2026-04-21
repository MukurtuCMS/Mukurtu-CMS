<?php

declare(strict_types=1);

namespace Drupal\search_api\Event;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\search_api\IndexInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a foreign relationships mapping event.
 */
final class MappingForeignRelationshipsEvent extends Event {

  /**
   * The index whose foreign relationships are mapped.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Reference to the foreign relationships mapping.
   *
   * @var array
   */
  protected $foreignRelationshipsMapping;

  /**
   * Cacheability associated with the foreign relationships mapping.
   *
   * @var \Drupal\Core\Cache\RefinableCacheableDependencyInterface
   */
  protected $cacheability;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index whose foreign relationships are mapped.
   * @param array $foreign_relationships_mapping
   *   The foreign relationships that were already found.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability associated with the foreign relationships mapping.
   */
  public function __construct(IndexInterface $index, array &$foreign_relationships_mapping, RefinableCacheableDependencyInterface $cacheability) {
    $this->index = $index;
    $this->foreignRelationshipsMapping = &$foreign_relationships_mapping;
    $this->cacheability = $cacheability;
  }

  /**
   * Retrieves the index whose foreign relationships are mapped.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index whose foreign relationships are mapped.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Retrieves a reference to the foreign relationships mapping.
   *
   * @return array[]
   *   A (numerically keyed) array of foreign relationship mappings. Each
   *   sub-array here represents a single known relationship. Such sub-arrays
   *   will have the following structure:
   *   - datasource: (string) The ID of the datasource which contains this
   *     relationship.
   *   - entity_type: (string) Entity type that is referred to from the index.
   *   - bundles: (array) Optional array of particular entity bundles that are
   *     referred to from the index. Empty array here means index refers to
   *     all the bundles.
   *   - property_path_to_foreign_entity: (string) Property path where the index
   *     refers to this entity.
   *   - field_name: (string) Name of the field on the referenced entity that
   *     actively participates in the search index.
   */
  public function &getForeignRelationshipsMapping(): array {
    return $this->foreignRelationshipsMapping;
  }

  /**
   * Retrieves cacheability associated with the foreign relationships mapping.
   *
   * @return \Drupal\Core\Cache\RefinableCacheableDependencyInterface
   *   Cacheability associated with the foreign relationships mapping.
   */
  public function getCacheability(): RefinableCacheableDependencyInterface {
    return $this->cacheability;
  }

}
