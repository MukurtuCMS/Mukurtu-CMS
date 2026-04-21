<?php

namespace Drupal\Tests\term_merge\Functional\TestDoubles;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\term_merge\TermMergerInterface;

/**
 * Dummy TermMerger class used for testing purposes.
 */
class TermMergerDummy implements TermMergerInterface {

  /**
   * {@inheritdoc}
   */
  public function mergeIntoNewTerm(array $terms_to_merge, string $new_term_label): TermInterface {
    return new Term([], 'taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function mergeIntoTerm(array $terms_to_merge, TermInterface $target_term): void {
    // Deliberately left empty because dummies don't do anything.
  }

}
