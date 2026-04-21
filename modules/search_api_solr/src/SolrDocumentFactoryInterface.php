<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Item\ItemInterface;

/**
 * Defines an interface for Solr Document factories.
 */
interface SolrDocumentFactoryInterface {

  /**
   * Creates a SolrDocument data type from a Search API result Item.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The result item to be wrapped with the data type class.
   *
   * @return \Drupal\search_api_solr\Plugin\DataType\SolrDocument
   *   The wrapped item.
   */
  public function create(ItemInterface $item);

}
