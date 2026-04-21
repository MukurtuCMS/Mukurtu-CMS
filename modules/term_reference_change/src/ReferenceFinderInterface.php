<?php

namespace Drupal\term_reference_change;

use Drupal\taxonomy\TermInterface;

/**
 * Defines an interface for a service that finds entities referencing a term.
 */
interface ReferenceFinderInterface {

  /**
   * Finds and loads all entities with a reference to the given term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to find references to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   All entities referencing the given term.
   */
  public function findReferencesFor(TermInterface $term);

  /**
   * Finds all term reference fields.
   *
   * @return array
   *   Nested array of field names for taxonomy term entity reference fields.
   *   [entity type id][bundle id] = array of field names.
   */
  public function findTermReferenceFields();

}
