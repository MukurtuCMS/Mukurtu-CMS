<?php

namespace Drupal\search_api\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a field types mapped event.
 */
final class MappingFieldTypesEvent extends Event {

  /**
   * Reference to the field type mapping.
   *
   * @var array<string, string|false>
   */
  protected $fieldTypeMapping;

  /**
   * Constructs a new class instance.
   *
   * @param array<string, string|false> $fieldTypeMapping
   *   Reference to the field type mapping.
   */
  public function __construct(array &$fieldTypeMapping) {
    $this->fieldTypeMapping = &$fieldTypeMapping;
  }

  /**
   * Retrieves a reference to the field type mapping.
   *
   * @return array<string, string|false>
   *   An array mapping all known (and supported) Drupal data types to their
   *   corresponding Search API data types. A value of FALSE means that fields
   *   of that type should be ignored by the Search API.
   */
  public function &getFieldTypeMapping(): array {
    return $this->fieldTypeMapping;
  }

}
