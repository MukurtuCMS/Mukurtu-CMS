<?php

namespace Drupal\search_api_test_extraction\Plugin\search_api\processor;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Provides a test processor that defines custom properties.
 */
#[SearchApiProcessor(
  id: 'search_api_test_extraction_add_properties',
  label: new TranslatableMarkup('"Add properties" test'),
  stages: [
    'add_properties' => 20,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class TestAddPropertiesProcessor extends ProcessorPluginBase {

  /**
   * The name used for the test properties.
   */
  public const PROPERTY_NAME = 'search_api_test_extraction_add_properties';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    $properties[self::PROPERTY_NAME] = new ProcessorProperty([
      'label' => $this->t('Test property'),
      'type' => 'string',
      'processor_id' => $this->getPluginId(),
    ]);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $object = $item->getOriginalObject();
    $entity = NULL;
    if ($object instanceof EntityAdapter) {
      $entity = $object->getEntity();
    }

    $item_fields = $item->getFields(FALSE);
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item_fields, NULL, self::PROPERTY_NAME);
    $fields += $this->getFieldsHelper()
      ->filterForPropertyPath($item_fields, $item->getDatasourceId(), self::PROPERTY_NAME);
    foreach ($fields as $field) {
      $field->addValue(implode('-', ['foo', $entity->bundle(), count($fields)]));
    }
  }

}
