<?php

namespace Drupal\mukurtu_multipage_items\Plugin\search_api\processor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * Adds a boolean field indicating whether a node is a non-first multipage page.
 *
 * is_additional_mpi_page is TRUE when the node appears at delta > 0 in any
 * multipage item's field_pages. Filtering on is_additional_mpi_page = FALSE
 * collapses results to first-page-only (non-pages and first pages are kept).
 */
#[SearchApiProcessor(
  id: 'mukurtu_multipage_page_index',
  label: new TranslatableMarkup('Multipage page index'),
  description: new TranslatableMarkup('Adds a boolean field that is TRUE for nodes that are non-first pages of a multipage item, FALSE otherwise.'),
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
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $properties['is_additional_mpi_page'] = new ProcessorProperty([
        'label' => $this->t('Is additional multipage item page'),
        'description' => $this->t('TRUE if this node is a non-first page of a multipage item, FALSE otherwise.'),
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
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'is_additional_mpi_page');

    if (empty($fields)) {
      return;
    }

    // Resolve table and column names dynamically, matching the pattern used in
    // mukurtu_multipage_items_views_query_alter() to avoid hardcoding.
    $mapping = $this->entityTypeManager->getStorage('multipage_item')->getTableMapping();
    $table = $mapping->getFieldTableName('field_pages');
    $col = $mapping->getColumnNames('field_pages')['target_id'];

    $delta = $this->database->select($table, 'f')
      ->fields('f', ['delta'])
      ->condition('f.' . $col, $entity->id())
      ->orderBy('f.delta', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    // TRUE only for non-first pages (delta > 0); non-pages and first pages are FALSE.
    $is_additional = ($delta !== FALSE && (int) $delta > 0);

    foreach ($fields as $field) {
      $field->addValue($is_additional);
    }
  }

}
