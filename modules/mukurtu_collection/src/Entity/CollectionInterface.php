<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityInterface;

interface CollectionInterface {
  public function add(EntityInterface $entity);
  public function remove(EntityInterface $entity);
}
