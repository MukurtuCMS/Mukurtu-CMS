<?php

namespace Drupal\flat_taxonomy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermStorage;

/**
 * Provides method to flatten a vocabulary's terms.
 *
 * @package Drupal\flat_taxonomy
 */
class Flattener {

  /**
   * The entity manager.
   */
  protected EntityTypeManagerInterface $entityManager;

  /**
   * The taxonomy term storage.
   */
  protected TermStorage $taxonomyTermStorage;

  /**
   * Constructs a new Flattener instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
    $this->taxonomyTermStorage = $this->entityManager->getStorage('taxonomy_term');
  }

  /**
   * Flatten an entire vocabulary.
   */
  public function flatten(Vocabulary $vocabulary): void {
    $weight = 0;
    $tree = $this->taxonomyTermStorage->loadTree($vocabulary->id(), 0, 1, TRUE);

    foreach ($tree as $term) {
      $this->flattenSubtree($term, $weight);
    }
  }

  /**
   * Flatten a vocabulary subtree from the given term.
   */
  public function flattenSubtree(Term $term, int $weight): void {
    // Update the given term.
    $term->weight = $weight++;
    $term->parent = 0;
    $term->save();

    // Get the subtree from the given term.
    $tree = $this->taxonomyTermStorage->loadTree($term->bundle(), $term->id(), 1, TRUE);

    // Recursively flatten each children subtree.
    foreach ($tree as $term) {
      $this->flattenSubtree($term, $weight);
    }
  }

}
