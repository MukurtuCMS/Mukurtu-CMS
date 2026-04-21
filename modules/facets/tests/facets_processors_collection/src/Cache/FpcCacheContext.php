<?php

namespace Drupal\facets_processors_collection\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\facets\Processor\ProcessorInterface;

/**
 * Dummy cache context for the fpc_build_processor facet processor.
 *
 * Cache context IDs: fpc_build, fpc_sort, fpc_post_query.
 */
class FpcCacheContext implements CacheContextInterface {

  /**
   * Context type: build, sort or post_query.
   *
   * @var string
   */
  protected $type;

  /**
   * List of facet processing stages.
   *
   * @var array
   */
  protected static $processorStages = [
    ProcessorInterface::STAGE_POST_QUERY,
    ProcessorInterface::STAGE_BUILD,
    ProcessorInterface::STAGE_SORT,
  ];

  /**
   * Cache context type used by query type plugin.
   */
  protected const QUERY_PLUGIN = 'query_type_plugin';

  /**
   * FpcCacheContext constructor.
   *
   * @param string $type
   *   Context type sort or build.
   */
  public function __construct(string $type) {
    if (!in_array($type, static::getAllowedTypes())) {
      throw new \InvalidArgumentException('Valid types are: ' . implode(', ', static::getAllowedTypes()));
    }
    $this->type = $type;
  }

  /**
   * Get all allowed context types.
   *
   * @return array
   *   Array of context types: all processor stages + query_type plugin.
   */
  protected static function getAllowedTypes() {
    return array_merge(static::$processorStages, [static::QUERY_PLUGIN]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t(
      'FPC: cache context, cab be one of the following: %stages.',
      ['%stages' => implode(', ', static::getAllowedTypes())]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return 'fpc_' . $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
