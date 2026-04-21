<?php

namespace Drupal\search_api\Datasource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Plugin\IndexPluginInterface;

/**
 * Describes a source for search items.
 *
 * A datasource is used to abstract the type of data that can be indexed and
 * searched with the Search API. Content entities are supported by default (with
 * the \Drupal\search_api\Plugin\search_api\datasource\ContentEntity
 * datasource), but others can be added by other modules. Datasources provide
 * all kinds of metadata for search items of their type, as well as loading and
 * viewing functionality.
 *
 * Modules providing new datasources are also responsible for calling the
 * appropriate track*() methods on all indexes that use that datasource when an
 * item of that type is inserted, updated or deleted.
 *
 * Note that the two load methods in this interface do not receive the normal
 * combined item IDs (that also include the datasource ID), but only the raw,
 * datasource-specific IDs.
 *
 * @see \Drupal\search_api\Attribute\SearchApiDatasource
 * @see \Drupal\search_api\Datasource\DatasourcePluginManager
 * @see \Drupal\search_api\Datasource\DatasourcePluginBase
 * @see plugin_api
 */
interface DatasourceInterface extends IndexPluginInterface {

  /**
   * Retrieves the properties exposed by the underlying complex data type.
   *
   * Property names have to start with a letter or an underscore, followed by
   * any number of letters, numbers and underscores.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An associative array of property data types, keyed by the property name.
   */
  public function getPropertyDefinitions();

  /**
   * Loads an item.
   *
   * @param mixed $id
   *   The datasource-specific ID of the item.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|null
   *   The loaded item if it could be found, NULL otherwise.
   */
  public function load($id);

  /**
   * Loads multiple items.
   *
   * @param array $ids
   *   An array of datasource-specific item IDs.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface[]
   *   An associative array of loaded items, keyed by their
   *   (datasource-specific) IDs.
   */
  public function loadMultiple(array $ids);

  /**
   * Retrieves the unique ID of an object from this datasource.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An object from this datasource.
   *
   * @return string|null
   *   The datasource-internal, unique ID of the item. Or NULL if the given item
   *   is no valid item of this datasource.
   */
  public function getItemId(ComplexDataInterface $item);

  /**
   * Retrieves a human-readable label for an item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this controller's type.
   *
   * @return string|null
   *   Either a human-readable label for the item, or NULL if none is available.
   */
  public function getItemLabel(ComplexDataInterface $item);

  /**
   * Retrieves the item's bundle.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   *
   * @return string
   *   The bundle identifier of the item. Might be just the datasource
   *   identifier or a similar pseudo-bundle if the datasource does not contain
   *   any bundles.
   *
   * @see getBundles()
   */
  public function getItemBundle(ComplexDataInterface $item);

  /**
   * Retrieves the item's language.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   *
   * @return string
   *   The language code of this item.
   */
  public function getItemLanguage(ComplexDataInterface $item);

  /**
   * Retrieves a URL at which the item can be viewed on the web.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   *
   * @return \Drupal\Core\Url|null
   *   Either an object representing the URL of the given item, or NULL if the
   *   item has no URL of its own.
   */
  public function getItemUrl(ComplexDataInterface $item);

  /**
   * Checks whether a user has permission to view the given item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user session for which to check access, or NULL to check
   *   access for the current user.
   *
   * @return bool
   *   TRUE if access is granted, FALSE otherwise.
   *
   * @deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0.
   *   Use getItemAccessResult() instead.
   *
   * @see https://www.drupal.org/node/3051902
   */
  public function checkItemAccess(ComplexDataInterface $item, ?AccountInterface $account = NULL);

  /**
   * Checks whether a user has permission to view the given item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this datasource's type.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user session for which to check access, or NULL to check
   *   access for the current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function getItemAccessResult(ComplexDataInterface $item, ?AccountInterface $account = NULL);

  /**
   * Returns the available view modes for this datasource.
   *
   * @param string|null $bundle
   *   (optional) The bundle for which to return the available view modes. Or
   *   NULL to return all view modes for this datasource, across all bundles.
   *
   * @return array<string, string|\Stringable>
   *   An associative array of view mode labels, keyed by the view mode ID. Can
   *   be empty if it isn't possible to view items of this datasource.
   */
  public function getViewModes($bundle = NULL);

  /**
   * Retrieves the bundles associated to this datasource.
   *
   * @return string[]
   *   An associative array mapping the datasource's bundles' IDs to their
   *   labels. If the datasource doesn't contain any bundles, a single
   *   pseudo-bundle should be returned, usually equal to the datasource
   *   identifier (and label).
   */
  public function getBundles();

  /**
   * Returns the render array for the provided item and view mode.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The item to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the item.
   * @param string|null $langcode
   *   (optional) For which language the item should be rendered. Defaults to
   *   the language the item has been loaded in.
   *
   * @return array
   *   A render array for displaying the item.
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL);

  /**
   * Returns the render array for the provided items and view mode.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface[] $items
   *   The items to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the items.
   * @param string|null $langcode
   *   (optional) For which language the items should be rendered. Defaults to
   *   the language each item has been loaded in.
   *
   * @return array
   *   A render array for displaying the items.
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL);

  /**
   * Retrieves the entity type ID of items from this datasource, if any.
   *
   * @return string|null
   *   If items from this datasource are all entities of a single entity type,
   *   that type's ID; NULL otherwise.
   */
  public function getEntityTypeId();

  /**
   * Returns a list of IDs of items from this datasource.
   *
   * Returns all items IDs by default. However, to avoid issues for large data
   * sets, plugins should also implement a paging mechanism (the details of
   * which are up to the datasource to decide) which guarantees that all item
   * IDs can be retrieved by repeatedly calling this method with increasing
   * values for $page (starting with 0) until NULL is returned.
   *
   * @param int|null $page
   *   The zero-based page of IDs to retrieve, for the paging mechanism
   *   implemented by this datasource; or NULL to retrieve all items at once.
   *
   * @return string[]|null
   *   An array with datasource-specific item IDs (that is, raw item IDs not
   *   prefixed with the datasource ID); or NULL if there are no more items for
   *   this and all following pages.
   */
  public function getItemIds($page = NULL);

  /**
   * Determines whether this datasource can contain entity references.
   *
   * If this method returns TRUE, the Search API will attempt to mark items for
   * reindexing if indexed data in entities referenced by those items changes,
   * using the datasource property information and the
   * getAffectedItemsForEntityChange() method.
   *
   * @return bool
   *   TRUE if this datasource can contain entity references, FALSE otherwise.
   *
   * @see \Drupal\search_api\Datasource\DatasourceInterface::getAffectedItemsForEntityChange()
   * @see \Drupal\search_api\Utility\TrackingHelper::trackReferencedEntityUpdate()
   */
  public function canContainEntityReferences(): bool;

  /**
   * Identifies items affected by a change to a referenced entity.
   *
   * A "change" in this context means an entity getting updated or deleted. (It
   * won't get called for entities being inserted, as new entities cannot
   * already have references pointing to them.)
   *
   * This method usually doesn't have to return the specified entity itself,
   * even if it is part of this datasource. This method should instead only be
   * used to detect items that are indirectly affected by this change.
   *
   * For instance, if an index contains nodes, and nodes can contain tags (which
   * are taxonomy term references), and the search index contains the name of
   * the tags as one of its fields, then a change of a term name should result
   * in all nodes being reindexed that contain that term as a tag. So, the item
   * IDs of those nodes should be returned by this method (in case this
   * datasource contains them).
   *
   * This method will only be called if this datasource plugin returns TRUE in
   * canContainEntityReferences().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that just got changed.
   * @param array[] $foreign_entity_relationship_map
   *   Map of known entity relationships that exist in the index. Its structure
   *   is identical to the return value of the
   *   \Drupal\search_api\Utility\TrackingHelper::getForeignEntityRelationsMap()
   *   method.
   * @param \Drupal\Core\Entity\EntityInterface|null $original_entity
   *   (optional) The original entity before the change. If this argument is
   *   NULL, it means the entity got deleted.
   *
   * @return string[]
   *   Array of item IDs that are affected by the changes between $entity and
   *   $original_entity entities.
   *
   * @see \Drupal\search_api\Datasource\DatasourceInterface::canContainEntityReferences()
   */
  public function getAffectedItemsForEntityChange(EntityInterface $entity, array $foreign_entity_relationship_map, ?EntityInterface $original_entity = NULL): array;

  /**
   * Retrieves any dependencies of the given fields.
   *
   * @param array<string, string> $fields
   *   An array of property paths on this datasource, keyed by field IDs.
   *
   * @return array<string, array<string, list<string>>>
   *   An associative array containing the dependencies of the given fields. The
   *   array is keyed by field ID and dependency type, the values are arrays
   *   with dependency names.
   */
  public function getFieldDependencies(array $fields);

  /**
   * Returns the list cache contexts associated with this datasource.
   *
   * List cache contexts ensure that if items from a datasource are included in
   * a list that any caches containing this list are varied as necessary. For
   * example a view might contain a number of items from this datasource that
   * are visible only by users that have a certain role. These list cache
   * contexts will ensure that separate cached versions exist for users with
   * this role and without it. These contexts should be included whenever a list
   * is rendered that contains items from this datasource.
   *
   * @return string[]
   *   The list cache contexts associated with this datasource.
   */
  public function getListCacheContexts();

}
