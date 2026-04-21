<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Strips HTML tags from fulltext fields and decodes HTML entities.
 *
 * @SearchApiProcessor(
 *   id = "unique_filter",
 *   label = @Translation("Unique values filter"),
 *   description = @Translation("Ensures unique values for multi-valued fields"),
 *   stages = {
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -15,
 *     "preprocess_query" = -15,
 *   }
 * )
 */
class UniqueFilter extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function processField(FieldInterface $field) {
    parent::processField($field);

    $values = array_unique($field->getValues());
    $field->setValues($values);
  }

}
