<?php

namespace Drupal\facets\Result;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;

/**
 * The default implementation of the result interfaces.
 */
class Result implements ResultInterface {

  /**
   * The facet transliterate display value.
   *
   * @var string
   */
  public $transliterateDisplayValue;

  /**
   * The facet related to the result.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * The facet value.
   *
   * @var string
   */
  protected $displayValue;

  /**
   * The raw facet value.
   *
   * @var string
   */
  protected $rawValue;

  /**
   * The facet count.
   *
   * @var int
   */
  protected $count = 0;

  /**
   * Indicates if this is the additional result item for "missing".
   *
   * @var bool
   */
  protected $missing = FALSE;

  /**
   * Other filters that might become active if result item isn't "missing".
   *
   * @var array
   */
  protected $missingFilters = [];

  /**
   * The Url object.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * Is this a selected value or not.
   *
   * @var bool
   */
  protected $active = FALSE;

  /**
   * Children results.
   *
   * @var \Drupal\facets\Result\ResultInterface[]
   */
  protected $children = [];

  /**
   * Private property for term weight.
   * @var int
   */
  protected $termWeight;

  /**
   * Storage for implementation-specific data.
   *
   * @var array
   */
  protected $storage = [];

  /**
   * Constructs a new result value object.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet related to the result.
   * @param mixed $raw_value
   *   The raw value.
   * @param mixed $display_value
   *   The formatted value.
   * @param int $count
   *   The amount of items.
   */
  public function __construct(FacetInterface $facet, $raw_value, $display_value, $count) {
    $this->facet = $facet;
    $this->rawValue = $raw_value;
    $this->displayValue = $display_value;
    $this->count = (int) $count;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayValue() {
    return $this->displayValue;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawValue() {
    return $this->rawValue;
  }

  /**
   * {@inheritdoc}
   */
  public function getCount() {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function setCount($count) {
    $this->count = (int) $count;
  }

  /**
   * {@inheritdoc}
   */
  public function isMissing(): bool {
    return $this->missing;
  }

  /**
   * {@inheritdoc}
   */
  public function setMissing(bool $missing) {
    $this->missing = $missing;
  }

  /**
   * {@inheritdoc}
   */
  public function getMissingFilters(): array {
    return $this->missingFilters;
  }

  /**
   * {@inheritdoc}
   */
  public function setMissingFilters(array $filters) {
    $this->missingFilters = array_filter($filters, static function ($filter) {
      return $filter !== '!';
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl(Url $url) {
    $this->url = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveState($active) {
    $this->active = $active;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return $this->active;
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayValue($display_value) {
    $this->displayValue = $display_value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChildren(array $children) {
    $this->children = $children;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    return $this->children;
  }

  /**
   * {@inheritdoc}
   */
  public function setTermWeight(int $weight) {
    $this->termWeight = $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getTermWeight() {
    return $this->termWeight;
  }

  /**
   * Returns true if the value has active children(selected).
   *
   * @return bool
   *   A boolean indicating the active state of children.
   */
  public function hasActiveChildren() {
    foreach ($this->getChildren() as $child) {
      if ($child->isActive() || $child->hasActiveChildren()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFacet() {
    return $this->facet;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * {@inheritdoc}
   */
  public function setStorage(array $storage) {
    $this->storage = $storage;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get($property) {
    return NestedArray::getValue($this->storage, (array) $property);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property, $value) {
    NestedArray::setValue($this->storage, (array) $property, $value, TRUE);
    return $this;
  }

}
