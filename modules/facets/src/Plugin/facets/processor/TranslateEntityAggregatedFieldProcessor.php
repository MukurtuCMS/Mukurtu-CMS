<?php

namespace Drupal\facets\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Plugin\facets\facet_source\SearchApiDisplay;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Transforms the results to show the translated entity label.
 *
 * @FacetsProcessor(
 *   id = "translate_entity_aggregated_fields",
 *   label = @Translation("Transform entity ID in aggregated field to label"),
 *   description = @Translation("Display the entity label instead of its ID (for example the term name instead of the taxonomy term ID) in aggregated fields."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class TranslateEntityAggregatedFieldProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity_type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The config manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, ConfigManagerInterface $config_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configManager = $config_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('config.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $field_identifier = $facet->getFieldIdentifier();
    $entity_type_ids = [];
    $allowed_values = [];
    $language_interface = $this->languageManager->getCurrentLanguage();

    // Support multiple entities when using Search API.
    if ($facet->getFacetSource() instanceof SearchApiDisplay) {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $facet->getFacetSource()->getIndex();
      /** @var \Drupal\search_api\Item\Field $field */
      $field = $index->getField($field_identifier);

      foreach ($field->getConfiguration()['fields'] as $field_configuration) {
        $parts = explode(':', $field_configuration);
        if ($parts[0] !== 'entity') {
          throw new \InvalidArgumentException('Data type must be in the form of "entity:ENTITY_TYPE/FIELD_NAME."');
        }
        $parts = explode('/', $parts[1]);
        $entity_type_id = $parts[0];
        $field = $parts[1];
        $entity_type_ids[] = $entity_type_id;

        $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
        $field_storage = $definition_update_manager->getFieldStorageDefinition($field, $entity_type_id);
        if ($field_storage && $field_storage->getType() === 'entity_reference') {
          /** @var \Drupal\facets\Result\ResultInterface $result */
          $ids = [];
          foreach ($results as $delta => $result) {
            $ids[$delta] = $result->getRawValue();
          }

          if ($field_storage instanceof FieldStorageDefinitionInterface) {
            if ($field !== 'type') {
              // Load all indexed entities of this type.
              $entity_type_id = $field_storage->getSettings()['target_type'];
              $entities = $this->entityTypeManager
                ->getStorage($entity_type_id)
                ->loadMultiple($ids);
              $access = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
              $this->checkEntitiesAccess($entities, $facet, $access);

              // Loop over all results.
              foreach ($results as $i => $result) {
                if (!isset($entities[$ids[$i]])) {
                  continue;
                }

                /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
                $entity = $entities[$ids[$i]];
                // Check for a translation of the entity and load that
                // instead if one's found.
                if ($entity instanceof TranslatableInterface && $entity->hasTranslation($language_interface->getId())) {
                  $entity = $entity->getTranslation($language_interface->getId());
                }
                $facet->addCacheableDependency($entity);
                // Overwrite the result's display value.
                $results[$i]->setDisplayValue($entity->label());
              }
            }
          }
        }
      }
      // If no values are found for the current field, try to see if this is a
      // bundle field.
      foreach ($entity_type_ids as $entity) {
        $list_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity);
        $facet->addCacheTags(['entity_bundles']);
        if (!empty($list_bundles)) {
          foreach ($list_bundles as $key => $bundle) {
            $allowed_values[$key] = $bundle['label'];
          }
          $this->overWriteDisplayValues($results, $allowed_values);
        }
      }
    }

    return $results;
  }

  /**
   * Overwrite the display value of the result with a new text.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $results
   *   An array of results to work on.
   * @param array $replacements
   *   An array of values that contain possible replacements for the original
   *   values.
   *
   * @return \Drupal\facets\Result\ResultInterface[]
   *   The changed results.
   */
  protected function overWriteDisplayValues(array $results, array $replacements) {
    /** @var \Drupal\facets\Result\ResultInterface $a */
    foreach ($results as &$a) {
      if (isset($replacements[$a->getRawValue()])) {
        $a->setDisplayValue($replacements[$a->getRawValue()]);
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $facet_source = $facet->getFacetSource();

    if ($facet_source instanceof SearchApiFacetSourceInterface) {
      /** @var \Drupal\search_api\Item\Field $field */
      $field_identifier = $facet->getFieldIdentifier();
      $field = $facet_source->getIndex()->getField($field_identifier);

      if ($field->getPropertyPath() === 'aggregated_field') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_interface']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // This will work unless the Search API Query uses "wrong" caching. Ideally
    // we would set a cache tag to invalidate the cache whenever a translatable
    // entity is added or changed. But there's no tag in drupal yet.
    return Cache::PERMANENT;
  }

}
