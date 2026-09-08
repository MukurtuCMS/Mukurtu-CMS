<?php

namespace Drupal\mukurtu_core_paragraph_prune_test\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\mukurtu_core\Entity\PrunesEmptyParagraphsTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class exercising PrunesEmptyParagraphsTrait through a real save.
 */
class PruneTestContent extends Node {

  use PrunesEmptyParagraphsTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->pruneEmptyParagraphs('field_sections');
  }

}
