<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mukurtu_collection\Entity\CollectionInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
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
      ->setDescription(t('Large collections may benefit from more internal structure or organization. This is done using sub-collections and may reflect existing physical arrangement (eg: boxes, folders, subfolders), topical groupings, or any other arrangement that will help users navigate the collection. Sub-collections can be multiple levels deep.	</br>Select "Select Content" to choose from existing collections. Sub-collections can also be added using the "+ New Sub-collection" button when viewing the collection. Sub-collections will be displayed in the order they are added, and can be manually arranged by dragging them into the desired order.'))
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
      ->setDescription(t('A featured image that is used on the collection page and in previews across the site. The image may be drawn from content in the collection, or selected to complement the collection.	</br>Select "Add media" to select or upload an image.'))
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
      ->setDescription(t('Source provides a reference to the organization or individual responsible for the holding, description, origination, or contribution of the collection. Examples include institutions (e.g.,: "Library of Congress, American Folklife Center"), or donors (e.g.,: "Donated by John Smith"). Maximum 255 characters.'))
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

    $definitions['field_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription(t('The history, story, explanation, provenance, arrangement information, or other description information about the collection. This is often based on existing collection descriptions and may include finding aids and other supplementary documentation.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_items_in_collection'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Items in Collection'))
      ->setDescription(t('The content found in the collection. Collections most commonly focus on digital heritage items, but content of any type can be included.	</br>Select "Select Content" to choose from existing site content. Content will be displayed in the order they are added, and can be manually arranged by dragging them into the desired order.'))
      ->setSettings([
        'target_type' => 'node',
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => NULL,
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
      ->setDescription(t('Keywords are used to tag collections to ensure they are discoverable when searching or browsing. 	As you type, existing keywords will be displayed. </br>Select an existing keyword or enter a new one. To include additional keywords, select "Add another item".'))
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
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_related_content'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Content'))
      ->setDescription(t('Collections can be related to any other site content when there is a connection that is important to show. Eg: another collection from the same donor. </br>Note that this field is not used to indicate content in the collection. See the items in collection field instead. </br>Select "Select Content" to choose from existing site content.'))
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
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_summary'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Summary'))
      ->setDescription(t('A short summary of the collection. The summary should supplement the title. The summary is displayed as part of the collection preview when browsing the site. Maximum 255 characters.'))
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
      ->setDescription(t('A detailed, interactive mapping tool that allows placing and drawing multiple locations related to a collection. Locations can be single points, paths, rectangles, or free-form polygons. Each location can be given a basic label. This field is also used for the browse by map tools. </br>Note that this mapping data will be shared with the same users or visitors as the rest of the collection. If the location is sensitive, carefully consider using this field.	</br>Use the tools shown on the map to place, draw, edit, and delete points and shapes. Once a point or shape has been placed, select it to add a description if needed.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_coverage_description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Location Description')
      ->setDescription(t('A descriptive field to provide additional context and depth to the location(s) connected to the collection.	</br>This HTML field can support rich text and embedded media assets using the editing toolbar.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_location'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Location'))
      ->setDescription(t('A named place, or places, that are closely connected to the collection. Examples include the location where a photo was taken, places named in a story, or the site where an object was created.	</br>As you type, existing locations will be displayed. Select an existing location or enter a new one. To include additional locations, select "Add another item".'))
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
      ->setDescription(t('This field will apply all of the Labels from the selected Local Contexts Project(s) to the collection.	</br>Select one or more Local Contexts Projects.'))
      ->setCardinality(-1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_local_contexts_labels_and_notices'] = BaseFieldDefinition::create('local_contexts_label_and_notice')
      ->setLabel(t('Local Contexts Labels and Notices'))
      ->setDescription(t('This field allows selective application of one or more Labels from any available Local Contexts Project to the collection.	</br>Select one or more Labels from the appropriate Local Contexts Project. If a complete project has already been selected, do not also select individual Labels from the same project.'))
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
