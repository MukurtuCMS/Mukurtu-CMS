<?php

namespace Drupal\mukurtu_local_contexts\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds label-inherited projects to the indexed Local Contexts Project.
 */
#[SearchApiProcessor(
  id: 'mukurtu_local_contexts_effective_projects',
  label: new TranslatableMarkup('Local Contexts Effective Projects'),
  description: new TranslatableMarkup('Includes projects inferred from an individually-applied Local Contexts label/notice so they match the same filters as directly-applied projects.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class LocalContextsEffectiveProjectsProcessor extends ProcessorPluginBase {

  /**
   * Constructs a new LocalContextsEffectiveProjectsProcessor.
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
      if ($entity_type_id && $this->hasLocalContextsProjectsField($entity_type_id, $datasource)) {
        $definition = [
          'label' => $this->t('Local Contexts Effective Projects'),
          'description' => $this->t('Projects directly applied to the content, plus any inferred from an individually-applied Local Contexts label/notice.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
          'is_list' => TRUE,
        ];
        $properties['mukurtu_local_contexts_effective_projects'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * Checks if any bundle in the datasource has the LC projects field.
   *
   * The field_local_contexts_projects field is defined per content-type
   * subclass via baseFieldDefinitions(), not on every node bundle, so we
   * need to check per-bundle field definitions.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   *
   * @return bool
   *   TRUE if any bundle in the datasource has the field.
   */
  protected function hasLocalContextsProjectsField(string $entity_type_id, DatasourceInterface $datasource): bool {
    foreach (array_keys($datasource->getBundles()) as $bundle) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      if (isset($field_definitions['field_local_contexts_projects'])) {
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

    if (!($entity instanceof ContentEntityInterface) || !$entity->hasField('field_local_contexts_projects')) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'mukurtu_local_contexts_effective_projects');

    if (empty($fields)) {
      return;
    }

    $projectIds = [];
    foreach ($entity->get('field_local_contexts_projects') as $projectItem) {
      if (!empty($projectItem->value)) {
        $projectIds[] = $projectItem->value;
      }
    }

    if ($entity->hasField('field_local_contexts_labels_and_notices')) {
      foreach ($entity->get('field_local_contexts_labels_and_notices') as $labelItem) {
        if (empty($labelItem->value)) {
          continue;
        }
        [$projectId] = explode(':', $labelItem->value, 2);
        $projectIds[] = $projectId;
      }
    }

    foreach (array_unique($projectIds) as $projectId) {
      foreach ($fields as $field) {
        $field->addValue($projectId);
      }
    }
  }

}
