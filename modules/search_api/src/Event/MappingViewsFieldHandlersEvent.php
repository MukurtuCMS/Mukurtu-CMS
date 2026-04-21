<?php

namespace Drupal\search_api\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a mapping Views field handlers event.
 */
final class MappingViewsFieldHandlersEvent extends Event {

  /**
   * The Views field handler mapping.
   *
   * @var array
   */
  protected $fieldHandlerMapping;

  /**
   * Constructs a new class instance.
   *
   * @param array $handlerMapping
   *   The Views field handler mapping.
   */
  public function __construct(array &$handlerMapping) {
    $this->fieldHandlerMapping = &$handlerMapping;
  }

  /**
   * Retrieves a reference to the Views field handler mapping.
   *
   * @return array
   *   An associative array with property data types as the keys and Views field
   *   handler definitions as the values (that is, just the inner "field"
   *   portion of Views data definition items). In some cases the value might
   *   also be NULL instead, to indicate that properties of this type shouldn't
   *   have field handlers. The data types in the keys might also contain
   *   asterisks (*) as wildcard characters. Data types with wildcards will be
   *   matched only if no specific type exists, and longer type patterns will be
   *   tried before shorter ones. The "*" mapping therefore is the default if no
   *   other match could be found.
   */
  public function &getFieldHandlerMapping(): array {
    return $this->fieldHandlerMapping;
  }

}
