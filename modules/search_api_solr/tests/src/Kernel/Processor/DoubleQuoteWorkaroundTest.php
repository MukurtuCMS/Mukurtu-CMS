<?php

namespace Drupal\Tests\search_api_solr\Kernel\Processor;

use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;

/**
 * Tests the "Double Quote Workaround" processor.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api_solr\Plugin\search_api\processor\DoubleQuoteWorkaround
 */
class DoubleQuoteWorkaroundTest extends ProcessorTestBase {

  use SolrBackendTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * The query helper.
   *
   * @var \Drupal\search_api_solr\Utility\StreamingExpressionQueryHelper
   */
  protected $queryHelper;

  /**
   * The Search API query.
   *
   * @var \Drupal\search_api\Query\Query
   */
  protected $query;

  /**
   * The streaming expression builder.
   *
   * @var \Drupal\search_api_solr\Utility\StreamingExpressionBuilder
   */
  protected $expressionBuilder;

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
    parent::setUp('double_quote_workaround');
    $this->enableSolrServer();

    $backend = $this->index->getServerInstance()->getBackend();
    $config = $backend->getConfiguration();
    // Streaming expressions are only supported by Solr Cloud.
    $config['connector'] = 'solr_cloud';
    $backend->setConfiguration($config);
    $this->queryHelper = \Drupal::getContainer()->get('search_api_solr.streaming_expression_query_helper');
    $this->query = $this->queryHelper->createQuery($this->index);
    $this->expressionBuilder = $this->queryHelper->getStreamingExpressionBuilder($this->query);
  }

  /**
   * Tests double quote workaround.
   */
  public function testDoubleQuoteWorkaround() {
    $processor = $this->index->getProcessor('double_quote_workaround');
    $configuration = $processor->getConfiguration();

    $replacement = $configuration['replacement'];
    $this->assertEquals(
      '|9999999998|',
      $replacement
    );

    // Set fields to process.
    $configuration['fields'] = ['title'];
    $processor->setConfiguration($configuration);
    $this->index->setProcessors(['double_quote_workaround' => $processor]);
    $this->index->save();

    $streaming_expression =
      $this->expressionBuilder->search(
        $this->expressionBuilder->_collection(),
        'q=' . $this->expressionBuilder->_field_escaped_value('search_api_datasource', 'entity:entity_test_mulrev_changed'),
        'fq="' . $this->expressionBuilder->_field_escaped_value('title', 'double "quotes" within the text', /* phrase */FALSE) . '"',
        'fl="' . $this->expressionBuilder->_field('title') . '"',
        'sort="' . $this->expressionBuilder->_field('search_api_id') . ' DESC"',
        'qt="/export"'
      );

    $this->assertEquals(
      'search(drupal, q=ss_search_api_datasource:entity\:entity_test_mulrev_changed, fq="tm_X3b_und_title:\\"double ' . $replacement . 'quotes' . $replacement . ' within the text\\"", fl="tm_X3b_und_title", sort="ss_search_api_id DESC", qt="/export")',
      $streaming_expression
    );

    $this->assertEquals(
      'double "quotes" within the text',
      $this->processor->decodeStreamingExpressionValue('double ' . $replacement . 'quotes' . $replacement . ' within the text')
    );
  }

}
