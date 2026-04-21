<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api_solr\Plugin\search_api\processor\Property\DummyFieldProperty;

/**
 * Adds dummy fields to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "solr_dummy_fields",
 *   label = @Translation("Solr dummy fields"),
 *   description = @Translation("Adds dummy fields to all datasources to register a pseudo field names that get their values via API, for example hook_search_api_solr_documents_alter()."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
class DummyFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Dummy field'),
        'description' => $this->t('Adds dummy field that gets its values via API, for example hook_search_api_solr_documents_alter(). To have these values as part of the result set you need to enable "Retrieve result data from Solr" in the server edit form.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ];
      $properties['dummy_field'] = new DummyFieldProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'dummy_field');
    foreach ($fields as $field) {
      $configuration = $field->getConfiguration();
      $field->addValue($configuration['dummy_value']);
    }
  }

}
