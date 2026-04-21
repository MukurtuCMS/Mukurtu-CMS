<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Plugin\search_api\processor\Property\AddURLProperty;

/**
 * Adds the item's URL to the indexed data.
 */
#[SearchApiProcessor(
  id: 'add_url',
  label: new TranslatableMarkup('URL field'),
  description: new TranslatableMarkup("Adds the item's URL to the indexed data."),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class AddURL extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('URI'),
        'description' => $this->t('A URI where the item can be accessed'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['search_api_url'] = new AddURLProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $url = $item->getDatasource()->getItemUrl($item->getOriginalObject());
    if ($url) {
      $fields = $item->getFields(FALSE);
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($fields, NULL, 'search_api_url');
      foreach ($fields as $field) {
        $config = $field->getConfiguration();
        $url->setAbsolute(!empty($config['absolute']));
        $field->addValue($url->toString(TRUE)->getGeneratedUrl());
      }
    }
  }

}
