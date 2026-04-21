<?php

namespace Drupal\Tests\term_merge\Functional\TestDoubles;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

/**
 * Term merger mock class used for testing purposes.
 */
class TermMergerMock extends TermMergerDummy {

  /**
   * {@inheritdoc}
   */
  public function mergeIntoNewTerm(array $terms_to_merge, string $new_term_label): TermInterface {
    return new Term([], 'taxonomy_term');
  }

}
