<?php

namespace Drupal\search_api\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a mapping Views handlers event.
 */
final class MappingViewsHandlersEvent extends Event {

  /**
   * The Views handler mapping.
   *
   * @var array
   */
  protected $handlerMapping;

  /**
   * Constructs a new class instance.
   *
   * @param array $handlerMapping
   *   The Views handler mapping.
   */
  public function __construct(array &$handlerMapping) {
    $this->handlerMapping = &$handlerMapping;
  }

  /**
   * Retrieves a reference to the Views handler mapping.
   *
   * @return array
   *   An associative array with data types as the keys and Views table data
   *   definition items as the values. In addition to all normally defined
   *   Search API data types, keys can also be "options" for any field with an
   *   options list, "entity" for general entity-typed fields or
   *   "entity:ENTITY_TYPE" (with "ENTITY_TYPE" being the machine name of an
   *   entity type) for entities of that type.
   */
  public function &getHandlerMapping(): array {
    return $this->handlerMapping;
  }

}
