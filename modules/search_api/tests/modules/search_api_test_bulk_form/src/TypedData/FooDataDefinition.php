<?php

namespace Drupal\search_api_test_bulk_form\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Provides a typed data definition class for describing 'foo'.
 */
class FooDataDefinition extends MapDataDefinition {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return [
      'foo' => DataDefinition::createFromDataType('any'),
    ];
  }

}
