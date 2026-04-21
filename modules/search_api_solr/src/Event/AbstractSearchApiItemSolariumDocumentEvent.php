<?php

namespace Drupal\search_api_solr\Event;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Solarium\Core\Query\DocumentInterface;

/**
 * Search API Solr event base class.
 */
abstract class AbstractSearchApiItemSolariumDocumentEvent extends AbstractIndexAwareEvent {

  /**
   * The search_api query.
   *
   * @var \Drupal\search_api\Item\ItemInterface
   */
  protected $searchApiItem;

  /**
   * The solarium document.
   *
   * @var \Solarium\Core\Query\DocumentInterface
   */
  protected $solariumDocument;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Item\ItemInterface $search_api_item
   *   The search_api item.
   * @param \Solarium\Core\Query\DocumentInterface $solarium_document
   *   The solarium document.
   */
  public function __construct(ItemInterface $search_api_item, DocumentInterface $solarium_document, IndexInterface $index) {
    parent::__construct($index);
    $this->searchApiItem = $search_api_item;
    $this->solariumDocument = $solarium_document;
  }

  /**
   * Retrieves the search_api item.
   *
   * @return \Drupal\search_api\Item\ItemInterface
   *   The search_api item.
   */
  public function getSearchApiItem() : ItemInterface {
    return $this->searchApiItem;
  }

  /**
   * Retrieves the solarium document.
   *
   * @return \Solarium\Core\Query\DocumentInterface
   *   The solarium document.
   */
  public function getSolariumDocument() : DocumentInterface {
    return $this->solariumDocument;
  }

}
