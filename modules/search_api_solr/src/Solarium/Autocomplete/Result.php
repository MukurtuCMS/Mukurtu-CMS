<?php

namespace Drupal\search_api_solr\Solarium\Autocomplete;

use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Query\Result\QueryType as BaseResult;

/**
 * Autocomplete query result.
 */
class Result extends BaseResult {

  /**
   * Component results.
   *
   * @var array
   */
  protected $components;

  /**
   * Get all component results.
   *
   * @return array
   *   The component results.
   */
  public function getComponents() {
    $this->parseResponse();

    return $this->components;
  }

  /**
   * Get a component result by key.
   *
   * @param string $key
   *   The component key.
   *
   * @return mixed
   *   The component value.
   */
  public function getComponent($key) {
    $this->parseResponse();

    if (isset($this->components[$key])) {
      return $this->components[$key];
    }

    return NULL;
  }

  /**
   * Get spellcheck component result.
   *
   * This is a convenience method that maps presets to getComponent.
   *
   * @return \Solarium\Component\Result\Spellcheck\Result|null
   *   The spellcheck component result.
   */
  public function getSpellcheck() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK);
  }

  /**
   * Get suggester component result.
   *
   * This is a convenience method that maps presets to getComponent.
   *
   * @return \Solarium\Component\Result\Suggester\Result|null
   *   The suggester component result.
   */
  public function getSuggester() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_SUGGESTER);
  }

  /**
   * Get terms component result.
   *
   * This is a convenience method that maps presets to getComponent.
   *
   * @return \Solarium\Component\Result\Terms\Result|null
   *   The terms component result.
   */
  public function getTerms() {
    return $this->getComponent(ComponentAwareQueryInterface::COMPONENT_TERMS);
  }

}
