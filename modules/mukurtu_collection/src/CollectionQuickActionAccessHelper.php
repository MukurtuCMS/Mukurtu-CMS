<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mukurtu_collection\Controller\MukurtuAddItemToCollectionController;
use Drupal\node\NodeInterface;

/**
 * Determines, cheaply and per-node, whether a node can be added to a
 * collection the current user can edit.
 *
 * MukurtuAddItemToCollectionController::getValidCollections() runs two
 * entity queries plus a load-and-access-check of every candidate collection,
 * per node. That's fine for a single node's "Add to Collection" page, but
 * rendering a browse listing of 20-50 cards would otherwise repeat that cost
 * once per row. This service computes "collections the current user can
 * edit, and what they contain" once per request (and across requests via the
 * Cache API), so per-row eligibility becomes an in-memory lookup.
 */
class CollectionQuickActionAccessHelper {

  /**
   * The current user's editable collections, keyed by collection node ID,
   * each value a [target_nid => TRUE] map of items already in that
   * collection. NULL until computed.
   *
   * @var array|null
   */
  protected ?array $editableCollections = NULL;

  /**
   * Cache tags collected while building the editable-collections set.
   *
   * @var array
   */
  protected array $cacheTags = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Whether the current user has at least one editable collection that
   * doesn't already contain the given node.
   */
  public function hasAddableCollectionFor(NodeInterface $node): bool {
    if (!MukurtuAddItemToCollectionController::isValidCollectionItemBundle($node)) {
      return FALSE;
    }
    foreach ($this->getEditableCollections() as $cid => $items) {
      if ((int) $cid === (int) $node->id()) {
        // A collection can't be added to itself.
        continue;
      }
      if (!isset($items[$node->id()])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Cache tags this helper's result set depends on, for the current user.
   *
   * Callers should merge these into the render cache metadata of anything
   * built from hasAddableCollectionFor(), alongside the 'user' cache
   * context (this helper's result is inherently per-account).
   */
  public function getCacheTags(): array {
    $this->getEditableCollections();
    return $this->cacheTags;
  }

  /**
   * Loads (and memoizes, per-request and per-user) the collections the
   * current user can edit.
   *
   * @return array
   *   [collection nid => [item nid => TRUE, ...]].
   */
  protected function getEditableCollections(): array {
    if ($this->editableCollections !== NULL) {
      return $this->editableCollections;
    }

    $uid = $this->currentUser->id();
    if (!$uid) {
      return $this->editableCollections = [];
    }

    $cid = 'mukurtu_collection:editable_collections:' . $uid;
    if ($cached = $this->cache->get($cid)) {
      $this->cacheTags = $cached->data['tags'];
      return $this->editableCollections = $cached->data['collections'];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'collection')
      ->accessCheck(TRUE)
      ->execute();

    $editable = [];
    $tags = [];
    foreach ($storage->loadMultiple($nids) as $collection) {
      $tags = array_merge($tags, $collection->getCacheTags());
      if (!$collection->access('update', $this->currentUser)) {
        continue;
      }
      $items = [];
      foreach ($collection->get(MUKURTU_COLLECTION_FIELD_NAME_ITEMS) as $item) {
        if (!empty($item->target_id)) {
          $items[$item->target_id] = TRUE;
        }
      }
      $editable[$collection->id()] = $items;
    }

    // Bounded lifetime as a safety net for a brand-new collection becoming
    // eligible without any existing collection node being saved (the tags
    // above cover every other membership/ownership change immediately).
    $this->cache->set($cid, ['collections' => $editable, 'tags' => $tags], time() + 3600, $tags);
    $this->cacheTags = $tags;

    return $this->editableCollections = $editable;
  }

}
