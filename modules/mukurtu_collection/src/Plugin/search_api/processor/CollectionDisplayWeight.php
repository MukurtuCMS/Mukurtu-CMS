<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_collection\CollectionOrder;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the collection's display weight from the collection order settings.
 *
 * The /collections views sort on this before title, so a custom order holds
 * across pages. Only collection nodes get a value.
 */
#[SearchApiProcessor(
  id: 'mukurtu_collection_display_weight',
  label: new TranslatableMarkup('Collection display weight'),
  description: new TranslatableMarkup('Adds the weight that orders collections on the Collections page.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class CollectionDisplayWeight extends ProcessorPluginBase {

  /**
   * The collection order service.
   */
  protected CollectionOrder $collectionOrder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->collectionOrder = $container->get('mukurtu_collection.collection_order');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $properties[CollectionOrder::FIELD_NAME] = new ProcessorProperty([
        'label' => $this->t('Collection display weight'),
        'description' => $this->t('The position of a collection on the Collections page.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ]);
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

    if (!($entity instanceof NodeInterface) || $entity->bundle() !== 'collection') {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), CollectionOrder::FIELD_NAME);

    $weight = $this->collectionOrder->getWeight((int) $entity->id());
    foreach ($fields as $field) {
      $field->addValue($weight);
    }
  }

}
