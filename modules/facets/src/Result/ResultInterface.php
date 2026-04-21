<?php

namespace Drupal\facets\Result;

use Drupal\Core\Url;

/**
 * The interface defining what a facet result should look like.
 */
interface ResultInterface {

  /**
   * Returns the facet related to the result.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet related to the result.
   */
  public function getFacet();

  /**
   * Returns the raw value as present in the index.
   *
   * @return string
   *   The raw value of the result.
   */
  public function getRawValue();

  /**
   * Returns the display value as present in the index.
   *
   * @return string
   *   The formatted value of the result.
   */
  public function getDisplayValue();

  /**
   * Returns the count for the result.
   *
   * @return int
   *   The amount of items for the result.
   */
  public function getCount();

  /**
   * Sets the count for the result.
   *
   * @param int $count
   *   The amount of items for the result.
   */
  public function setCount($count);

  /**
   * Set if this result represents the "missing" facet item.
   *
   * @return bool
   *   True if this result represents the missing facet item.
   */
  public function isMissing(): bool;

  /**
   * Returns true if this result represents the "missing" facet item.
   *
   * @param bool $missing
   *   True if this result represents the missing facet item.
   */
  public function setMissing(bool $missing);

  /**
   * Get the filter values of the non-missing values to be inverted.
   *
   * @return array
   *   The filter values of the non-missing values to be inverted.
   */
  public function getMissingFilters(): array;

  /**
   * Set the filter values of the non-missing values to be inverted.
   *
   * @param array $filters
   *   The filter values of the non-missing values to be inverted.
   */
  public function setMissingFilters(array $filters);

  /**
   * Returns the url.
   *
   * @return \Drupal\Core\Url
   *   The url of the search page with the facet url appended.
   */
  public function getUrl();

  /**
   * Sets the url.
   *
   * @param \Drupal\Core\Url $url
   *   The url of the search page with the facet url appended.
   */
  public function setUrl(Url $url);

  /**
   * Indicates that the value is active (selected).
   *
   * @param bool $active
   *   A boolean indicating the active state.
   */
  public function setActiveState($active);

  /**
   * Returns true if the value is active (selected).
   *
   * @return bool
   *   A boolean indicating the active state.
   */
  public function isActive();

  /**
   * Returns true if the value has active children(selected).
   *
   * @return bool
   *   A boolean indicating the active state of children.
   */
  public function hasActiveChildren();

  /**
   * Overrides the display value of a result.
   *
   * @param string $display_value
   *   Override display value.
   */
  public function setDisplayValue($display_value);

  /**
   * Sets children results.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $children
   *   The children to be added.
   */
  public function setChildren(array $children);

  /**
   * Returns children results.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   The children results.
   */
  public function getChildren();

  /**
   * Sets the term weight.
   *
   * @param int $weight
   *   The term weight.
   */
  public function setTermWeight(int $weight);

  /**
   * Returns the term weight.
   *
   * @return int
   *   The term weight.
   */
  public function getTermWeight();

  /**
   * Returns the entire set of arbitrary data.
   *
   * @return array
   *   The entire set of arbitrary data storage for this result.
   */
  public function getStorage();

  /**
   * Sets the entire set of arbitrary data.
   *
   * @param array $storage
   *   The entire set of arbitrary data to store for this result.
   *
   * @return $this
   */
  public function setStorage(array $storage);

  /**
   * Gets any arbitrary property.
   *
   * @param string|array $property
   *   Properties are often stored as multi-dimensional associative arrays. If
   *   $property is a string, it will return $storage[$property]. If $property
   *   is an array, each element of the array will be used as a nested key. If
   *   $property = ['foo', 'bar'] it will return $storage['foo']['bar'].
   *
   * @return mixed
   *   The property, or the default if the property does not exist.
   */
  public function get($property);

  /**
   * Sets a value to an arbitrary property.
   *
   * @param string|array $property
   *   Properties are often stored as multi-dimensional associative arrays. If
   *   $property is a string, it will use $storage[$property] = $value. If
   *   $property is an array, each element of the array will be used as a nested
   *   key. If $property = ['foo', 'bar'] it will use
   *   $storage['foo']['bar'] = $value.
   * @param mixed $value
   *   The value to set.
   *
   * @return $this
   */
  public function set($property, $value);

}
