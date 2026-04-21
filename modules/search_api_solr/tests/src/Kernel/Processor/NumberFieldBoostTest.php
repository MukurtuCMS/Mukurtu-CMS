<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\Tests\search_api\Kernel\Processor\NumberFieldBoostTest as SearchApiNumberFieldBoostTest;

/**
 * Tests the "Number field boost" processor.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\NumberFieldBoost
 *
 * @group search_api_solr
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\NumberFieldBoost
 */
class NumberFieldBoostTest extends SearchApiNumberFieldBoostTest {

  use SolrBackendTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_solr',
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();
    $this->enableSolrServer();
  }

}
