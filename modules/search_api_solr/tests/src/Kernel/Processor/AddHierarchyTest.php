<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\Tests\search_api\Kernel\Processor\AddHierarchyTest as SearchApiAddHierarchyTest;

/**
 * Tests the "Hierarchy" processor.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 *
 * @group search_api_solr
 *
 * @coversDefaultClass \Drupal\search_api\Plugin\search_api\processor\AddHierarchy
 */
class AddHierarchyTest extends SearchApiAddHierarchyTest {

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

  /**
   * Tests regression.
   */
  public function testRegression3059312() {
    $this->markTestSkipped('This test makes no sense on Solr.');
  }

}
