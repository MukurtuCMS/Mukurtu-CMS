<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\Core\Entity\EntityInterface;

interface CollectionInterface {
  /**
   * Add an entity to a collection.
   *
   * @param EntityInterface $entity
   *   The entity to add to the collection.
   *
   * @return void
   */
  public function add(EntityInterface $entity);

  /**
   * Remove an entity from a collection.
   *
   * @param EntityInterface $entity
   *   The entity to remove from the collection.
   *
   * @return void
   */
  public function remove(EntityInterface $entity);

}
