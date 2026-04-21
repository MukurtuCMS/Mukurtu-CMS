<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\node\Entity\NodeType;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;

/**
 * Tests usages of Solr payloads.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\HtmlFilter
 */
class HtmlFilterTest extends ProcessorTestBase {

  use NodeCreationTrait;
  use SolrBackendTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'search_api_solr',
    'search_api_solr_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('html_filter');
    $this->enableSolrServer();

    $this->installConfig(['filter']);

    // Create a node type for testing.
    $type = NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ]);
    $type->save();
  }

  /**
   * Tests term boosts.
   */
  public function testBoostTerms() {
    $solr_major_version = $this->server->getBackend()->getSolrConnector()->getSolrMajorVersion();
    if (version_compare($solr_major_version, '6', '<')) {
      $this->markTestSkipped('Term boosting requires Solr >= 6.');
      return;
    }

    $this->assertArrayHasKey('html_filter', $this->index->getProcessors(), 'HTML filter processor is added.');

    $this->createNode([
      'type' => 'page',
      'title' => 'Beautiful Page 1',
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => 'Beautiful <b>Page</b> 2',
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => 'Beautiful Page 3',
    ]);

    $this->index->reindex();
    $this->indexItems();

    $query = new Query($this->index);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $query = new Query($this->index);
    $query->keys(['beautiful']);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/1:en',
      'entity:node/2:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    // Rerank query based on payloads for HTML tags boosts on match.
    $query = new Query($this->index);
    $query->keys(['page']);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/2:en',
      'entity:node/1:en',
      'entity:node/3:en',
    ], array_keys($result->getResultItems()));

    $this->createNode([
      'type' => 'page',
      'title' => "d'avion",
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => "<b>d'avion<b>",
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => '😀😎👾',
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => '<b>More| strange " characters 😀😎👾<b>',
    ]);

    $this->createNode([
      'type' => 'page',
      'title' => 'More| strange " characters 😀😎👾',
    ]);

    $this->indexItems();

    $query = new Query($this->index);
    $query->keys(["d'avion"]);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([
      'entity:node/5:en',
      'entity:node/4:en',
    ], array_keys($result->getResultItems()));

    if (version_compare($solr_major_version, '8', '>=')) {
      $query = new Query($this->index);
      $query->keys(['😀😎👾']);
      $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
      $query->sort('search_api_id');
      $query->getParseMode()->setConjunction('OR');
      $result = $query->execute();
      $this->assertEquals([
        'entity:node/7:en',
        'entity:node/6:en',
        'entity:node/8:en',
      ], array_keys($result->getResultItems()));
    }

    $this->createNode([
      'type' => 'page',
      'title' => "<b>VeryLongStingsWithMoreThanOneHundredCharactersOrMoreShouldNotNeitherBeIndexedAsTextNorAsBoostedTokenAndShouldNotLeadToExceptionsDuringIndexing<b>",
    ]);

    $this->indexItems();

    $query = new Query($this->index);
    $query->keys(['VeryLongStingsWithMoreThanOneHoundredCharactersShouldNotNeitherBeIndexedAsTextNorAsBoostedTokenAndShouldNotLeadToExceptionsDuringIndexing']);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([], array_keys($result->getResultItems()));

    $this->createNode([
      'type' => 'page',
      'title' => "<b>VeryLongStingsWithMoreThanOneHundredCharactersOrMoreShouldNotNeitherBeIndexedAsTextNorAsBoostedTokenAndShouldNotLeadToExceptionsDuringIndexing<b>",
    ]);

    $this->indexItems();

    $query = new Query($this->index);
    $query->keys(['VeryLongStingsWithMoreThanOneHundredCharactersOrMoreShouldNotNeitherBeIndexedAsTextNorAsBoostedTokenAndShouldNotLeadToExceptionsDuringIndexing']);
    $query->sort('search_api_relevance', QueryInterface::SORT_DESC);
    $query->sort('search_api_id');
    $query->getParseMode()->setConjunction('OR');
    $result = $query->execute();
    $this->assertEquals([], array_keys($result->getResultItems()));
  }

}
