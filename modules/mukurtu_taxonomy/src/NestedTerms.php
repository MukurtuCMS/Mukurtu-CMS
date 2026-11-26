<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Tracks nested terms, and saves and restores a vocabulary's nesting.
 *
 * The nesting is saved before a vocabulary is made flat, so it can be
 * restored if nested terms are allowed again.
 *
 * Whether a vocabulary is flat is the flat_taxonomy module's per-vocabulary
 * third-party setting. Mukurtu replaces that module's vocabulary form builder,
 * which flattened every term with no confirmation and no way back (#2519).
 */
class NestedTerms {

  /**
   * Key-value collection holding one snapshot per vocabulary ID.
   */
  const SNAPSHOT_COLLECTION = 'mukurtu_taxonomy.nested_terms_snapshot';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyValueFactoryInterface $keyValueFactory,
    protected Connection $database,
  ) {}

  /**
   * Whether terms in the vocabulary can't be nested.
   */
  public function isFlat(VocabularyInterface $vocabulary): bool {
    return (bool) $vocabulary->getThirdPartySetting('flat_taxonomy', 'flat');
  }

  /**
   * Gets a vocabulary's name in the current language, for messages.
   *
   * Admin routes pass the vocabulary without translations, and a vocabulary
   * loaded with them must never be saved, so the name is loaded separately.
   */
  public function label(string $vid): string {
    return (string) $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid)?->label();
  }

  /**
   * Gets the IDs of terms in a vocabulary that have a parent.
   *
   * @return int[]
   *   Term IDs.
   */
  public function getNestedTermIds(string $vid): array {
    $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vid)
      ->condition('parent.target_id', 0, '>')
      ->execute();
    return array_map('intval', array_values($ids));
  }

  /**
   * Counts the terms in a vocabulary that have a parent.
   */
  public function countNestedTerms(string $vid): int {
    return count($this->getNestedTermIds($vid));
  }

  /**
   * Gets the IDs of a term's immediate children.
   *
   * @return int[]
   *   Term IDs.
   */
  public function getChildIds(int $tid): array {
    $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('parent.target_id', $tid)
      ->execute();
    return array_map('intval', array_values($ids));
  }

  /**
   * Moves a term's immediate children under another parent.
   *
   * Their own children move with them. A child is left where it is if it's in
   * $skip, or if moving it would make it an ancestor of itself.
   *
   * @param int $tid
   *   The term whose children move.
   * @param int $new_parent
   *   The new parent's ID, or 0 for the top level.
   * @param int[] $skip
   *   IDs of children to leave alone.
   */
  public function moveChildren(int $tid, int $new_parent, array $skip = []): void {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ancestors = $new_parent ? array_map('intval', array_keys($storage->loadAllParents($new_parent))) : [];
    foreach ($storage->loadMultiple($this->getChildIds($tid)) as $child) {
      $child_id = (int) $child->id();
      if (in_array($child_id, $skip, TRUE) || in_array($child_id, $ancestors, TRUE)) {
        continue;
      }
      $child->set('parent', $new_parent);
      $child->save();
    }
  }

  /**
   * Saves the parent and weight of every nested term in a vocabulary.
   *
   * Only call this when the vocabulary has nested terms, so that making a
   * vocabulary with no nesting flat never replaces an older snapshot.
   *
   * Reads the tables directly rather than loading every nested term, which
   * matters for large vocabularies.
   */
  public function snapshot(string $vid): void {
    $query = $this->database->select('taxonomy_term__parent', 'p');
    $query->join('taxonomy_term_field_data', 'd', '[d].[tid] = [p].[entity_id] AND [d].[default_langcode] = 1');
    $query->fields('p', ['entity_id', 'parent_target_id']);
    $query->fields('d', ['weight']);
    $query->condition('p.bundle', $vid);
    $query->condition('p.deleted', 0);
    $query->condition('p.parent_target_id', 0, '>');
    $query->orderBy('p.entity_id');

    $snapshot = [];
    foreach ($query->execute() as $row) {
      $snapshot[(int) $row->entity_id] = [
        'parent' => (int) $row->parent_target_id,
        'weight' => (int) $row->weight,
      ];
    }
    $this->store()->set($vid, $snapshot);
  }

  /**
   * Gets a vocabulary's saved structure.
   *
   * @return array
   *   Parent and weight, keyed by term ID. Empty if nothing is saved.
   */
  public function getSnapshot(string $vid): array {
    return $this->store()->get($vid, []);
  }

  /**
   * Whether a vocabulary has a saved structure to restore.
   */
  public function hasSnapshot(string $vid): bool {
    return !empty($this->getSnapshot($vid));
  }

  /**
   * Deletes a vocabulary's saved structure.
   */
  public function deleteSnapshot(string $vid): void {
    $this->store()->delete($vid);
  }

  /**
   * Gets the snapshot store.
   */
  protected function store(): KeyValueStoreInterface {
    return $this->keyValueFactory->get(self::SNAPSHOT_COLLECTION);
  }

}
