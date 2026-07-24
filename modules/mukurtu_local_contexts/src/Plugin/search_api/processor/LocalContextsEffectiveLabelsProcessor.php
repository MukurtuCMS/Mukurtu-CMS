<?php

namespace Drupal\mukurtu_local_contexts\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds project-inherited labels/notices to the indexed Local Contexts Label.
 */
#[SearchApiProcessor(
  id: 'mukurtu_local_contexts_effective_labels',
  label: new TranslatableMarkup('Local Contexts Effective Labels'),
  description: new TranslatableMarkup('Includes labels and notices inherited from an applied Local Contexts Project so they match the same filters as directly-applied labels.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class LocalContextsEffectiveLabelsProcessor extends ProcessorPluginBase {

  /**
   * Constructs a new LocalContextsEffectiveLabelsProcessor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
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
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if ($entity_type_id && $this->hasLocalContextsLabelsField($entity_type_id, $datasource)) {
        $definition = [
          'label' => $this->t('Local Contexts Effective Labels'),
          'description' => $this->t('Labels and notices directly applied to the content, plus any inherited from an applied Local Contexts Project.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
          'is_list' => TRUE,
        ];
        $properties['mukurtu_local_contexts_effective_labels'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * Checks if any bundle in the datasource has the LC labels field.
   *
   * The field_local_contexts_labels_and_notices field is defined per
   * content-type subclass via baseFieldDefinitions(), not on every node
   * bundle, so we need to check per-bundle field definitions.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   *
   * @return bool
   *   TRUE if any bundle in the datasource has the field.
   */
  protected function hasLocalContextsLabelsField(string $entity_type_id, DatasourceInterface $datasource): bool {
    foreach (array_keys($datasource->getBundles()) as $bundle) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      if (isset($field_definitions['field_local_contexts_labels_and_notices'])) {
        return TRUE;
      }
    }
    return FALSE;
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

    if (!($entity instanceof ContentEntityInterface) || !$entity->hasField('field_local_contexts_labels_and_notices')) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'mukurtu_local_contexts_effective_labels');

    if (empty($fields)) {
      return;
    }

    $keys = [];
    foreach ($entity->get('field_local_contexts_labels_and_notices') as $labelItem) {
      if (!empty($labelItem->value)) {
        $keys[] = $labelItem->value;
      }
    }

    if ($entity->hasField('field_local_contexts_projects')) {
      foreach ($entity->get('field_local_contexts_projects') as $projectItem) {
        if (empty($projectItem->value)) {
          continue;
        }
        $project = new LocalContextsProject($projectItem->value);
        $keys = array_merge($keys, $project->getLabelAndNoticeKeys());
      }
    }

    foreach (array_unique($keys) as $key) {
      foreach ($fields as $field) {
        $field->addValue($key);
      }
    }
  }

}
