<?php

namespace Drupal\mukurtu_protocol\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Splits compound cultural protocol field values into individual protocol IDs.
 */
#[SearchApiProcessor(
  id: 'mukurtu_protocol_cultural_protocol_ids',
  label: new TranslatableMarkup('Cultural Protocol IDs'),
  description: new TranslatableMarkup('Splits the compound cultural protocol field into individual protocol IDs for proper faceting.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class CulturalProtocolIds extends ProcessorPluginBase {

  /**
   * Constructs a new CulturalProtocolIds processor.
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
      if ($entity_type_id && $this->hasCulturalProtocolsField($entity_type_id, $datasource)) {
        $definition = [
          'label' => $this->t('Cultural Protocol IDs'),
          'description' => $this->t('Individual protocol IDs extracted from the cultural protocols field.'),
          'type' => 'string',
          'processor_id' => $this->getPluginId(),
          'is_list' => TRUE,
        ];
        $properties['mukurtu_cultural_protocol_ids'] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * Checks if any bundle in the datasource has the cultural protocols field.
   *
   * The field_cultural_protocols field is defined via bundleFieldDefinitions(),
   * not baseFieldDefinitions(), so we need to check per-bundle field
   * definitions.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   *
   * @return bool
   *   TRUE if any bundle in the datasource has the field.
   */
  protected function hasCulturalProtocolsField(string $entity_type_id, DatasourceInterface $datasource): bool {
    foreach (array_keys($datasource->getBundles()) as $bundle) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      if (isset($field_definitions['field_cultural_protocols'])) {
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

    if (!($entity instanceof ContentEntityInterface) || !$entity->hasField('field_cultural_protocols')) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'mukurtu_cultural_protocol_ids');

    if (empty($fields)) {
      return;
    }

    $protocol_value = $entity->get('field_cultural_protocols')->protocols ?? '';
    $protocol_ids = CulturalProtocolItem::unformatProtocols($protocol_value);

    foreach ($fields as $field) {
      foreach ($protocol_ids as $id) {
        $field->addValue((string) $id);
      }
    }
  }

}
