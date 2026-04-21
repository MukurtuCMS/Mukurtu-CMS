<?php

namespace Drupal\search_api_solr_test\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;

/**
 * A typed data definition class for describing widgets.
 */
class WidgetDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $this->propertyDefinitions['widget_types'] = \Drupal::typedDataManager()
      ->createListDataDefinition('string')
      ->setLabel('Widget Types');
    return $this->propertyDefinitions;
  }

}
