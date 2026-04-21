<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Represents an exception that occurs in Search API Solr.
 */
class SearchApiSolrConflictingEntitiesException extends SearchApiSolrException {

  /**
   * Array with the conflicting entities.
   *
   * @var \Drupal\search_api_solr\SolrConfigInterface[]
   */
  protected $conflictingEntities = [];

  /**
   * Get the conflicting entities.
   *
   * @return \Drupal\search_api_solr\SolrConfigInterface[]
   *   Array with the conflicting entities.
   */
  public function getConflictingEntities(): array {
    return $this->conflictingEntities;
  }

  /**
   * Set the conflicting entities.
   *
   * @param \Drupal\search_api_solr\SolrConfigInterface[] $conflictingEntities
   *   Array with the conflicting entities.
   */
  public function setConflictingEntities(array $conflictingEntities): void {
    $this->conflictingEntities = $conflictingEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    $conflicts = '<ul>';
    foreach ($this->getConflictingEntities() as $entity) {
      $link = new FormattableMarkup('<li><a href="' . $entity->toUrl('collection')->toString() . '">@label</a></li>', ['@label' => $entity->label()]);
      $conflicts .= $link;
    }
    return $conflicts . '</ul>';
  }

}
