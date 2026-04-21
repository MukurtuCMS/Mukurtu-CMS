<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Server;
use Solarium\QueryType\Extract\Query;

/**
 * Test tika extension based PDF extraction.
 *
 * @group search_api_solr
 */
class SearchApiSolrExtractionTest extends SolrBackendTestBase {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->moduleExtensionList = \Drupal::getContainer()
      ->get('extension.list.module');
  }

  /**
   * Test tika extension based PDF extraction.
   */
  public function testBackend() {
    $filepath = $this->moduleExtensionList->getPath('search_api_solr_test') . '/assets/test_extraction.pdf';
    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $content = $backend->extractContentFromFile($filepath);
    $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $content);
    $this->assertStringContainsString('The extraction seems working!', $content);

    $content = $backend->extractContentFromFile($filepath, Query::EXTRACT_FORMAT_TEXT);
    $this->assertStringStartsNotWith('<?xml version="1.0" encoding="UTF-8"?>', $content);
    $this->assertStringContainsString('The extraction seems working!', $content);
  }

}
