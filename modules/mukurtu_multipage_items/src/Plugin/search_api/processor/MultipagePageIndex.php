<?php

namespace Drupal\mukurtu_multipage_items\Plugin\search_api\processor;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a field indicating a node's position within a multipage item.
 *
 * Value is -1 for nodes that are not part of any multipage item, 0 for the
 * first page, and higher integers for subsequent pages. Filtering on
 * multipage_page_delta < 1 collapses results to first-page-only.
 */
#[SearchApiProcessor(
  id: 'mukurtu_multipage_page_index',
  label: new TranslatableMarkup('Multipage page index'),
  description: new TranslatableMarkup('Adds an integer field indicating whether a node is a multipage item page and its position (-1 = not a page, 0 = first page, 1+ = subsequent pages).'),
  stages: [
    'add_properties' => 0,
  ],
)]
class MultipagePageIndex extends ProcessorPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $properties['multipage_page_delta'] = new ProcessorProperty([
        'label' => $this->t('Multipage page delta'),
        'description' => $this->t('Position of this node in a multipage item (-1 if not a page, 0 if first page, 1+ for subsequent pages).'),
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

    if (!($entity instanceof NodeInterface)) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'multipage_page_delta');

    if (empty($fields)) {
      return;
    }

    $delta = $this->database->select('multipage_item__field_pages', 'f')
      ->fields('f', ['delta'])
      ->condition('f.field_pages_target_id', $entity->id())
      ->orderBy('f.delta', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $value = ($delta !== FALSE) ? (int) $delta : -1;

    foreach ($fields as $field) {
      $field->addValue($value);
    }
  }

}
