<?php

declare(strict_types=1);

namespace Drupal\search_api\Utility;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\search_api\Event\MappingForeignRelationshipsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides datasource-independent item change tracking functionality.
 */
class TrackingHelper implements TrackingHelperInterface {

  /**
   * {@inheritdoc}
   */
  public function trackReferencedEntityUpdate(EntityInterface $entity, bool $deleted = FALSE) {
    if (!empty($entity->search_api_skip_tracking)) {
      return;
    }

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = [];
    try {
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // Can't really happen, but play it safe to appease static code analysis.
    }

    // Original entity, if available.
    $original = NULL;
    if (!$deleted) {
      $original = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.2',
        fn () => $entity->getOriginal(),
        fn () => $entity->original ?? NULL,
      );
    }
    foreach ($indexes as $index) {
      // Do not track changes to referenced entities if the option has been
      // disabled.
      if (!$index->getOption('track_changes_in_references')) {
        continue;
      }

      // Map of foreign entity relations. Will get lazily populated as soon as
      // we actually need it.
      $map = NULL;
      foreach ($index->getDatasources() as $datasource_id => $datasource) {
        if (!$datasource->canContainEntityReferences()) {
          continue;
        }

        if ($map === NULL) {
          $map = $this->getForeignEntityRelationsMap($index);
          // If there are no foreign entities in the index, no need to continue.
          if (!$map) {
            break 1;
          }
        }

        $item_ids = $datasource->getAffectedItemsForEntityChange($entity, $map, $original);
        if (!empty($item_ids)) {
          $index->trackItemsUpdated($datasource_id, $item_ids);
        }
      }
    }
  }

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected FieldsHelperInterface $fieldsHelper,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cache
  ) {}

  /**
   * Analyzes the index fields and constructs a map of entity references.
   *
   * This map tries to record all ways that entities' values are indirectly
   * indexed by the given index. (That is, what items' indexed contents might be
   * affected by a given entity being updated or deleted.)
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to create the map.
   *
   * @return array[]
   *   A (numerically keyed) array of foreign relationship mappings. Each
   *   sub-array represents a single known relationship. Such sub-arrays will
   *   have the following structure:
   *   - datasource: (string) The ID of the datasource which contains this
   *     relationship.
   *   - entity_type: (string) The entity type that is referenced from the
   *     index.
   *   - bundles: (string[]) An optional array of particular entity bundles that
   *     are referred to from the index. An empty array here means that the
   *     index refers to all the bundles.
   *   - property_path_to_foreign_entity: (string) Property path where the index
   *     refers to this entity.
   *   - field_name: (string) Name of the field on the referenced entity that is
   *     indexed in the search index.
   */
  protected function getForeignEntityRelationsMap(IndexInterface $index): array {
    $cid = "search_api:{$index->id()}:foreign_entities_relations_map";

    $cache = $this->cache->get($cid);
    if ($cache) {
      return $cache->data;
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($index);
    $data = [];
    foreach ($index->getFields() as $field) {
      try {
        $datasource = $field->getDatasource();
      }
      catch (SearchApiException) {
        continue;
      }
      if (!$datasource) {
        continue;
      }

      $relation_info = [
        'datasource' => $datasource->getPluginId(),
        'entity_type' => NULL,
        'bundles' => NULL,
        'property_path_to_foreign_entity' => NULL,
      ];
      $seen_path_chunks = [];
      $property_definitions = $datasource->getPropertyDefinitions();
      $field_property = Utility::splitPropertyPath($field->getPropertyPath(), FALSE);
      for (; $field_property[0]; $field_property = Utility::splitPropertyPath($field_property[1] ?? '', FALSE)) {
        $property_definition = $this->fieldsHelper->retrieveNestedProperty($property_definitions, $field_property[0]);
        if (!$property_definition) {
          // Seems like we could not map it from the property path to some Typed
          // Data definition. In the absence of a better alternative, let's
          // simply disregard this field.
          break;
        }

        $seen_path_chunks[] = $field_property[0];

        if ($property_definition instanceof FieldItemDataDefinitionInterface
            && $property_definition->getFieldDefinition()->isComputed()) {
          // We cannot really deal with computed fields since we have no
          // knowledge about their internal logic. Thus we cannot process
          // this field any further.
          break;
        }

        if ($relation_info['entity_type'] && $property_definition instanceof FieldItemDataDefinitionInterface) {
          // Parent is an entity. Hence this level is fields of the entity.
          $cacheability->addCacheableDependency($property_definition->getFieldDefinition());

          $data[] = $relation_info + [
            'field_name' => $property_definition->getFieldDefinition()
              ->getName(),
          ];
        }

        $entity_reference = $this->isEntityReferenceDataDefinition($property_definition, $cacheability);
        if ($entity_reference) {
          // Unfortunately, the nested "entity" property for entity reference
          // fields comes without a bundles restriction, so we need to copy the
          // bundles information from the level above (on the field itself), if
          // any.
          if ($relation_info['entity_type'] === $entity_reference['entity_type']
              && empty($entity_reference['bundles'])
              && !empty($relation_info['bundles'])
              && $field_property[0] === 'entity') {
            $entity_reference['bundles'] = $relation_info['bundles'];
          }
          $relation_info = $entity_reference;
          $relation_info['property_path_to_foreign_entity'] = implode(IndexInterface::PROPERTY_PATH_SEPARATOR, $seen_path_chunks);
          $relation_info['datasource'] = $datasource->getPluginId();
        }

        if ($property_definition instanceof ComplexDataDefinitionInterface) {
          $property_definitions = $this->fieldsHelper->getNestedProperties($property_definition);
        }
        else {
          // This item no longer has "nested" properties in its Typed Data
          // definition. Thus we cannot examine it any further than the current
          // point.
          break;
        }
      }
    }

    // Let other modules alter this information, potentially adding more
    // relationships.
    $event = new MappingForeignRelationshipsEvent($index, $data, $cacheability);
    $this->eventDispatcher->dispatch($event, SearchApiEvents::MAPPING_FOREIGN_RELATIONSHIPS);

    $this->cache->set($cid, $data, $cacheability->getCacheMaxAge(), $cacheability->getCacheTags());

    return $data;
  }

  /**
   * Determines whether the given property is a reference to an entity.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $property_definition
   *   The property to test.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   A cache metadata object to track any caching information necessary in
   *   this method call.
   *
   * @return array
   *   This method will return an empty array if $property is not an entity
   *   reference. Otherwise it will return an associative array with the
   *   following structure:
   *   - entity_type: (string) The entity type to which $property refers.
   *   - bundles: (array) A list of bundles to which $property refers. In case
   *     specific bundles cannot be determined or the $property points to all
   *     the bundles, this key will contain an empty array.
   */
  protected function isEntityReferenceDataDefinition(DataDefinitionInterface $property_definition, RefinableCacheableDependencyInterface $cacheability): array {
    $return = [];

    if ($property_definition instanceof FieldItemDataDefinitionInterface
        && $property_definition->getFieldDefinition()->getType() === 'entity_reference') {
      $field = $property_definition->getFieldDefinition();
      $cacheability->addCacheableDependency($field);

      $return['entity_type'] = $field->getSetting('target_type');
      $field_settings = $field->getSetting('handler_settings');
      $return['bundles'] = $field_settings['target_bundles'] ?? [];
    }
    elseif ($property_definition instanceof EntityDataDefinitionInterface) {
      $return['entity_type'] = $property_definition->getEntityTypeId();
      $return['bundles'] = $property_definition->getBundles() ?: [];
    }

    return $return;
  }

}
