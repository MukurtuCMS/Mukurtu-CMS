<?php

namespace Drupal\term_merge;

use Drupal\taxonomy\TermInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event used to notify subscribers that terms were merged.
 */
class TermsMergedEvent extends Event {

  /**
   * Array of terms. These terms are getting merged into $targetTerm.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $sourceTerms;

  /**
   * Target Term. All $sourceTerms are getting merged into this.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $targetTerm;

  /**
   * Constructor.
   *
   * @param array $source_terms
   *   Terms to merge.
   * @param \Drupal\taxonomy\TermInterface $target_term
   *   Target Term.
   */
  public function __construct(array $source_terms, TermInterface $target_term) {
    $this->sourceTerms = $source_terms;
    $this->targetTerm = $target_term;
  }

  /**
   * Retrieve the terms that are being merged into the target term.
   *
   * @return array
   *   An array of terms to merge.
   */
  public function getSourceTerms(): array {
    return $this->sourceTerms;
  }

  /**
   * Get the single target term.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   Single target term object.
   */
  public function getTargetTerm(): TermInterface {
    return $this->targetTerm;
  }

}
