<?php

namespace Drupal\mukurtu_community_records\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Adds a boolean field marking nodes that are community records.
 *
 * A community record is a node with field_mukurtu_original_record populated.
 * Filtering on is_community_record = FALSE hides community records and shows
 * only original records.
 */
#[SearchApiProcessor(
  id: 'mukurtu_community_record_flag',
  label: new TranslatableMarkup('Community record flag'),
  description: new TranslatableMarkup('Adds a boolean field that is TRUE for community records (nodes with an original record reference) and FALSE otherwise.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class CommunityRecordFlag extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $properties['is_community_record'] = new ProcessorProperty([
        'label' => $this->t('Is community record'),
        'description' => $this->t('TRUE if this node is a community record (references an original record), FALSE otherwise.'),
        'type' => 'boolean',
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

    if (!($entity instanceof NodeInterface)) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'is_community_record');

    if (empty($fields)) {
      return;
    }

    $is_cr = (bool) mukurtu_community_records_is_community_record($entity);

    foreach ($fields as $field) {
      $field->addValue($is_cr);
    }
  }

}
