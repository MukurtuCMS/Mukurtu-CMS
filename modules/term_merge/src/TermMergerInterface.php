<?php

namespace Drupal\term_merge;

use Drupal\taxonomy\TermInterface;

/**
 * Provides an interface for a term merger service.
 */
interface TermMergerInterface {

  /**
   * Merges two or more terms into a new term.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms_to_merge
   *   The terms to merge.
   * @param string $new_term_label
   *   The label of the new term.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The newly created term.
   */
  public function mergeIntoNewTerm(array $terms_to_merge, string $new_term_label): TermInterface;

  /**
   * Merges one or more terms into an existing term.
   *
   * @param array $terms_to_merge
   *   The terms to merge.
   * @param \Drupal\taxonomy\TermInterface $target_term
   *   The term to merge them into.
   */
  public function mergeIntoTerm(array $terms_to_merge, TermInterface $target_term): void;

}
