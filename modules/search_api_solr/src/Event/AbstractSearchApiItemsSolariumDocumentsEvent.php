<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Search API Solr event base class.
 */
abstract class AbstractSearchApiItemsSolariumDocumentsEvent extends Event {

  /**
   * The search_api query.
   *
   * @var \Drupal\search_api\Item\ItemInterface[]
   */
  protected $searchApiItems;

  /**
   * The solarium document.
   *
   * @var \Solarium\Core\Query\DocumentInterface[]
   */
  protected $solariumDocuments;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $search_api_items
   *   The search_api items.
   * @param \Solarium\Core\Query\DocumentInterface[] $solarium_documents
   *   The solarium documents.
   */
  public function __construct(array &$search_api_items, array &$solarium_documents) {
    $this->searchApiItems = &$search_api_items;
    $this->solariumDocuments = &$solarium_documents;
  }

  /**
   * Retrieves the search_api items.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   The search_api items.
   */
  public function getSearchApiItems() : array {
    return $this->searchApiItems;
  }

  /**
   * Retrieves the solarium documents.
   *
   * @return \Solarium\Core\Query\DocumentInterface[]
   *   The solarium documents.
   */
  public function getSolariumDocuments() : array {
    return $this->solariumDocuments;
  }

  /**
   * Sets the solarium documents.
   *
   * @param \Solarium\Core\Query\DocumentInterface[] $solarium_documents
   *   The solarium documents.
   */
  public function setSolariumDocuments(array $solarium_documents) : void {
    $this->solariumDocuments = $solarium_documents;
  }

}
