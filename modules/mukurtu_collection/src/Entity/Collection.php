<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\mukurtu_protocol\CulturalProtocolControlledTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Exception;

class Collection extends Node implements CollectionInterface, CulturalProtocolControlledInterface, MukurtuDraftInterface {
  use CulturalProtocolControlledTrait;
  use MukurtuDraftTrait;

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = self::getProtocolFieldDefinitions();

    // Add the drafts field.
    $definitions += static::draftBaseFieldDefinitions($entity_type);

    $definitions['field_child_collections'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sub-Collections'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'collection' => 'collection'
          ],
          'sort' => [
            'field' => 'title',
            'direction' => 'ASC'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_parent_collection'] = BaseFieldDefinition::create('entity_reference')
      ->setName('field_parent_collection')
      ->setLabel(t('Parent Collection'))
      ->setDescription('')
      ->setComputed(TRUE)
      ->setClass('Drupal\mukurtu_collection\Plugin\Field\MukurtuParentCollectionFieldItemsList')
      ->setTargetEntityTypeId('node')
      ->setTargetBundle('collection')
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE);

    $definitions['field_collection_image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Image'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image'
          ],
          'sort' => [
            'field' => '_none',
            'direction' => 'ASC'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t(''))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_items_in_collection'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Items in Collection'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'digital_heritage' => 'digital_heritage',
            'dictionary_word' => 'dictionary_word',
            'word_list' => 'word_list',
            'mukurtu_person' => 'mukurtu_person'
          ],
          'sort' => [
            'field' => 'title',
            'direction' => 'ASC'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => 'collection',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setDescription(t('Keywords provide added ways to group your content. They make it easier for users to search and retrieve content.'))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords'
          ],
          'sort' => [
            'field' => 'name',
            'direction' => 'asc'
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => '',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Content'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => NULL,
          'sort' => [
            'field' => '_none'
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => 'article',
        ]
      ])
      ->setDefaultValue('')
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_summary'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Summary'))
      ->setDescription(t(''))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Map Points'))
      ->setDescription(t(''))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('Location Description adds additional context to a Geocode address, and can be used instead of a Geocode Address if the location should be identified, but not precisely located on a map.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_location'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Location'))
      ->setDescription(t(''))
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'location' => 'location'
          ],
          'auto_create' => TRUE,
        ]
      ])
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_projects'] = BaseFieldDefinition::create('local_contexts_project')
      ->setLabel(t('Local Contexts Projects'))
      ->setDescription(t('Local Contexts projects from the Local Contexts Hub.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_labels_and_notices'] = BaseFieldDefinition::create('local_contexts_label_and_notice')
      ->setLabel(t('Local Contexts Labels and Notices'))
      ->setDescription(t('Local Contexts Labels and Notices from the Local Contexts Hub.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function add(EntityInterface $entity): void {
    $items = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
    $items[] = ['target_id' => $entity->id()];
    $this->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(EntityInterface $entity): void {
    $needle = $entity->id();
    $items = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->getValue();
    foreach ($items as $delta => $item) {
      if ($item['target_id'] == $needle) {
        unset($items[$delta]);
      }
    }
    $this->set(MUKURTU_COLLECTION_FIELD_NAME_ITEMS, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(): int {
    // Count the items in the children.
    $childCollectionsCount = 0;
    if ($childCollections = $this->getChildCollections()) {
      /**
       * @var \Drupal\mukurtu_collection\Entity\Collection $childCollection
       */
      foreach ($childCollections as $childCollection) {
        // Don't count things the user can't see.
        if ($childCollection->access('view')) {
          $childCollectionsCount += $childCollection->getCount();
        }
      }
    }

    // Count the items in this single collection.
    return $childCollectionsCount + $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->count();
  }

  /**
   * Get immediate (depth = 1) child collections.
   *
   * @return \Drupal\mukurtu_collection\Entity\CollectionInterface[]
   *   The array of child collection entities.
   */
  public function getChildCollections() {
    $collections = $this->get('field_child_collections')->referencedEntities() ?? NULL;
    return $collections;
  }

  /**
   * Get child collection IDs.
   *
   * @return array
   *   The array of child collection IDs.
   */
  public function getChildCollectionIds() {
    $collections = $this->get('field_child_collections')->getValue() ?? [];
    return array_column($collections, 'target_id');
  }

  /**
   * Set the child collections.
   *
   * @param array $child_collection_ids
   *  The array of child collection IDs.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   */
  public function setChildCollections(array $child_collection_ids) {
    return $this->set('field_child_collections', $child_collection_ids);
  }

  /**
   * Get the parent collection.
   *
   * @return null|\Drupal\mukurtu_collection\Entity\Collection
   *   Null if none, otherwise the parent collection entity.
   */
  public function getParentCollection() {
    $id = $this->getParentCollectionId();
    if ($id) {
      return $this->entityTypeManager()->getStorage('node')->load($id);
    }
    return NULL;
  }

  /**
   * Get the parent collection node ID.
   *
   * @return null|int
   *   Null if none, otherwise the parent collection id.
   */
  public function getParentCollectionId() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->condition('field_child_collections', $this->id(), '=')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Not in use at all.
    if (count($results) == 0) {
      return NULL;
    }

    return reset($results);
  }

  /**
   * Add a collection as a sub-collection.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The child collection.
   *
   * @return \Drupal\mukurtu_collection\Entity\Collection
   *   The parent of the newly added child collection.
   */
  public function addChildCollection(Collection $collection): Collection {
    $this->get('field_child_collections')->appendItem(['target_id' => $collection->id()]);
    return $this;
  }

  /**
   * Globally remove this collection as a subcollection. Saves the parent collection.
   */
  public function removeAsChildCollection(): Collection {
    if ($parent = $this->getParentCollection()) {
      $childCollectionsRefs = $parent->get('field_child_collections')->getValue();
      foreach ($childCollectionsRefs as $delta => $ref) {
        if ($ref['target_id'] == $this->id()) {
          unset($childCollectionsRefs[$delta]);
          $parent->set('field_child_collections', $childCollectionsRefs);
          try {
            $parent->save();
          } catch (Exception $e) {
          }
          return $this;
        }
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $parentCollection = $this->getParentCollection();
    $this->addCacheableDependency($parentCollection);
    if ($parentCollection) {
      $this->set('field_parent_collection', ['target_id' => $parentCollection->id()]);
    }
    else {
      $this->set('field_parent_collection', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Check for request to add as a subcollection.
    if (isset($this->values["_parent_collection"]) && $id = intval($this->values["_parent_collection"])) {
      /** @var \Drupal\mukurtu_collection\Entity\Collection $parentCollection */
      $parentCollection = $this->entityTypeManager()->getStorage('node')->load($id);
      if ($parentCollection && $parentCollection->access('update')) {
        $parentCollection->addChildCollection($this);
        $violationList = $parentCollection->validate();
        if ($violationList->count() == 0) {
          $parentCollection->save();
        } else {
          \Drupal::logger('mukurtu_collection')->error('Failed to add subcollection id:@subcollection to parent collection id:@parentcollection. Parent collection failed entity validation.', [
            '@subcollection' => $this->id(),
            '@parentcollection' => $id,
          ]);
        }
      }
    }

    // Invalid the cache of referenced entities
    // to trigger recalculation of the computed fields.
    $refs = $this->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS)->referencedEntities() ?? NULL;
    if (!empty($refs)) {
      foreach ($refs as $ref) {
        Cache::invalidateTags($ref->getCacheTagsToInvalidate());
      }
    }

    // Invalid the collection's cache as well.
    Cache::invalidateTags($this->getCacheTagsToInvalidate());
  }
}
