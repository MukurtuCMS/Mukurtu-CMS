<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Adds entity type to the indexed data.
 */
#[SearchApiProcessor(
  id: 'entity_type',
  label: new TranslatableMarkup('Entity type'),
  description: new TranslatableMarkup("Adds the item's entity type to the indexed data."),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class EntityType extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Entity type'),
        'description' => $this->t("The indexed item's entity type"),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['search_api_entity_type'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }
    if (!($entity instanceof EntityInterface)) {
      return;
    }

    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'search_api_entity_type');
    foreach ($fields as $field) {
      $field->addValue($entity->getEntityTypeId());
    }
  }

}
