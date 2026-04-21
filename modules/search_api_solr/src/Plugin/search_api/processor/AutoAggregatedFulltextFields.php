<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api_solr\Plugin\search_api\processor\Property\AutoAggregatedFulltextFieldProperty;

/**
 * Adds customized aggregations of existing fields to the index.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty
 *
 * @SearchApiProcessor(
 *   id = "auto_aggregated_fulltext_field",
 *   label = @Translation("Auto aggregated fulltext fields"),
 *   description = @Translation("Add automatic aggregations of all language-specific fulltext fields of the same kind to the index."),
 *   stages = {
 *     "add_properties" = 100,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AutoAggregatedFulltextFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Auto aggregated fulltext field'),
        'description' => $this->t('An automatic aggregation of all fulltext fields of the same kind of the same language.'),
        'type' => 'search_api_text',
        'processor_id' => $this->getPluginId(),
        // We use a multiple field to avoid issues with concatenated strings.
        'is_list' => TRUE,
      ];
      $properties['auto_aggregated_fulltext_field'] = new AutoAggregatedFulltextFieldProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'auto_aggregated_fulltext_field');

    foreach ($fields as $field) {
      $field->setValues([new TextValue('auto_aggregated_fulltext_field')]);
    }
  }

}
