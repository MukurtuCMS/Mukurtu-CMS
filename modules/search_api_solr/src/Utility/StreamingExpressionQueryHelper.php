<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\QueryHelper;

/**
 * Provides methods for creating streaming expressions.
 */
class StreamingExpressionQueryHelper extends QueryHelper {

  /**
   * Builds a streaming expression for the given Search API query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   *
   * @return \Drupal\search_api_solr\Utility\StreamingExpressionBuilder
   *   The StreamingExpressionBuilder object.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getStreamingExpressionBuilder(QueryInterface $query) {
    return new StreamingExpressionBuilder($query->getIndex());
  }

  /**
   * Applies a streaming expression for a given Search API query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   * @param \Drupal\search_api_solr\Utility\string $streaming_expression
   *   The streaming expression to set for this query.
   * @param \Drupal\search_api_solr\Utility\string $comment
   *   A comment of the streaming expression.
   */
  public function setStreamingExpression(QueryInterface $query, string $streaming_expression, string $comment = '') {
    if ($comment) {
      $query->setOption('solr_streaming_expression_comment', $comment);
    }
    $query->setOption('solr_streaming_expression', $streaming_expression);
  }

}
