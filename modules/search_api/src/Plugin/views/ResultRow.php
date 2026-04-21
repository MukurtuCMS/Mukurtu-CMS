<?php

namespace Drupal\search_api\Plugin\views;

use Drupal\views\ResultRow as ViewsResultRow;
use Drupal\views\ViewExecutable;

/**
 * A class representing a result row of a Search API-based view.
 *
 * @property string search_api_id
 * @property string search_api_datasource
 * @property string search_api_language
 * @property float search_api_relevance
 * @property string|null search_api_excerpt
 */
class ResultRow extends ViewsResultRow {

  /**
   * The lazy-loaded properties, as property names mapped to item methods.
   */
  public const LAZY_LOAD_PROPERTIES = [
    'search_api_id' => 'getId',
    'search_api_datasource' => 'getDatasourceId',
    'search_api_language' => 'getLanguage',
    'search_api_relevance' => 'getScore',
    'search_api_excerpt' => 'getExcerpt',
  ];

  /**
   * The lazy-loaded properties, as property names mapped to item methods.
   *
   * @var string[]
   *
   * @deprecated in search_api:8.x-1.29 and is removed from search_api:2.0.0.
   *   Use static::LAZY_LOAD_PROPERTIES instead.
   *
   * @see https://www.drupal.org/node/3342910
   */
  protected static $lazyLoad = [
    'search_api_id' => 'getId',
    'search_api_datasource' => 'getDatasourceId',
    'search_api_language' => 'getLanguage',
    'search_api_relevance' => 'getScore',
    'search_api_excerpt' => 'getExcerpt',
  ];

  // @codingStandardsIgnoreStart PSR2.Classes.PropertyDeclaration.Underscore

  /**
   * The Search API result item for this row.
   *
   * @var \Drupal\search_api\Item\ItemInterface
   */
  public $_item;

  /**
   * The original object for this row's result item, if retrieved.
   *
   * @var \Drupal\Core\TypedData\ComplexDataInterface|null
   */
  public $_object;

  /**
   * Extracted property values this result row, keyed by combined property path.
   *
   * @var \Drupal\Core\TypedData\TypedDataInterface[][]
   */
  public $_relationship_objects = [];

  /**
   * Array keeping track of the reference tree followed to obtain properties.
   *
   * Keyed by combined property path.
   *
   * @var int[][]
   */
  public $_relationship_parent_indices = [];

  // @codingStandardsIgnoreEnd

  /**
   * Implements the magic __isset() method to lazy-load certain properties.
   */
  public function __isset($name) {
    $properties = get_object_vars($this);
    return isset($properties[$name])
      || (isset(static::LAZY_LOAD_PROPERTIES[$name]) && $this->_item);
  }

  /**
   * Implements the magic __get() method to lazy-load certain properties.
   */
  public function __get($name) {
    $properties = get_object_vars($this);
    if (array_key_exists($name, $properties)) {
      return $properties[$name];
    }

    if (isset(static::LAZY_LOAD_PROPERTIES[$name]) && $this->_item) {
      $method = static::LAZY_LOAD_PROPERTIES[$name];
      return $this->_item->$method();
    }

    $class = get_class($this);
    trigger_error("Undefined property: $class::\$$name");
    return NULL;
  }

  /**
   * Implements the magic __sleep() method to remove the view from the entity.
   *
   * The "view" property is added by the "Rendered entity" row plugin in
   * \Drupal\search_api\Plugin\views\row\SearchApiRow::preRender() to make it
   * available to entity viewing code. It should not be needed elsewhere, but
   * can cause problems (including infinite loops) when Views results are
   * serialized (as done, for instance, by the Metatag module in some setups).
   *
   * @return string[]
   *   An array with the names of all object properties.
   */
  public function __sleep() {
    if (!empty($this->_object)) {
      $entity = $this->_object->getValue();
      if (($entity->view ?? NULL) instanceof ViewExecutable) {
        unset($entity->view);
      }
    }
    return array_keys(get_object_vars($this));
  }

}
