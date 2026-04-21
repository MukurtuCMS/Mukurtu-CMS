<?php

namespace Drupal\entity_browser\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Defines a bulk operation form element that works with entity browser.
 *
 * @ViewsField("entity_browser_search_api_select")
 */
class SearchApiSelectForm extends SelectForm {

  /**
   * {@inheritdoc}
   */
  public function getRowId(ResultRow $row) {
    $entity = $row->_object->getValue();
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

}
