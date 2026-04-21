<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\Tests\search_api\Kernel\Processor\ContentAccessTest as SearchApiContentAccessTest;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\ContentAccess
 */
class ContentAccessTest extends SearchApiContentAccessTest {

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
