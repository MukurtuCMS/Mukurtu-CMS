<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;

/**
 * Finds a term's parent on import, including on a later row (#2520).
 *
 * Rows import in file order, so a parent can be named before its row has
 * been imported. Those are recorded during the import and resolved again
 * once every row is in.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\process\TermParent
 * @see \Drupal\mukurtu_import\ImportBatchExecutable::import()
 */
class TermParentResolver {

  /**
   * Prefix of the key-value collection of parents still to resolve.
   *
   * Each import gets its own collection, with one entry per row, so
   * recording a row doesn't rewrite the others.
   */
  const COLLECTION = 'mukurtu_import.deferred_term_parents.';

  /**
   * How long an unfinished import's records are kept, in seconds.
   */
  const EXPIRE = 86400;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyValueExpirableFactoryInterface $keyValueExpirable,
  ) {}

  /**
   * Finds a parent term in a vocabulary by UUID, term ID, or unique name.
   *
   * @return int|null
   *   The term ID, or NULL if there's no match, or more than one by name.
   */
  public function resolve(string $vid, string $value): ?int {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    if (Uuid::isValid($value)) {
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('vid', $vid)
        ->condition('uuid', $value)
        ->execute();
      return $ids ? (int) reset($ids) : NULL;
    }

    if (ctype_digit($value)) {
      $term = $storage->load($value);
      if ($term && $term->bundle() === $vid) {
        return (int) $term->id();
      }
    }

    // Names aren't unique, especially once terms are nested, so a name
    // matching more than one term doesn't count.
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', $vid)
      ->condition('name', $value)
      ->range(0, 2)
      ->execute();
    return count($ids) === 1 ? (int) reset($ids) : NULL;
  }

  /**
   * Records a parent to resolve once every row has been imported.
   *
   * @param string $migration_id
   *   The import's migration ID.
   * @param array $source_ids
   *   The row's source ID values.
   * @param string $vid
   *   The vocabulary ID.
   * @param string $value
   *   The parent as given in the file.
   */
  public function defer(string $migration_id, array $source_ids, string $vid, string $value): void {
    $this->store($migration_id)->setWithExpire(
      hash('sha256', serialize($source_ids)),
      ['source_ids' => $source_ids, 'vid' => $vid, 'value' => $value],
      self::EXPIRE
    );
  }

  /**
   * Sets the parents recorded during an import, now that every row is in.
   *
   * @return int
   *   The number of terms whose parent still couldn't be found. They stay at
   *   the top level.
   */
  public function resolveDeferred(string $migration_id, MigrateIdMapInterface $id_map): int {
    $deferred = $this->store($migration_id)->getAll();
    $this->store($migration_id)->deleteAll();

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $unresolved = 0;
    foreach ($deferred as $item) {
      $destination = $id_map->lookupDestinationIds($item['source_ids']);
      $tid = $destination[0][0] ?? NULL;
      $term = $tid ? $storage->load($tid) : NULL;
      if (!$term) {
        // The row itself failed, and is reported as such.
        continue;
      }

      $parent = $this->resolve($item['vid'], $item['value']);
      // A parent that's the term itself, or nested under it, would loop.
      if (!$parent || isset($storage->loadAllParents($parent)[$term->id()])) {
        $unresolved++;
        continue;
      }
      $term->set('parent', $parent);
      $term->save();
    }
    return $unresolved;
  }

  /**
   * Gets the store of one import's parents still to resolve.
   */
  protected function store(string $migration_id): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirable->get(self::COLLECTION . $migration_id);
  }

}
