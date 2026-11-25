<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\EventSubscriber;

use Drupal\mukurtu_taxonomy\NestedTerms;
use Drupal\term_merge\TermsMergedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Moves the children of merged terms under the target term (#2520).
 *
 * The term_merge module deletes the merged terms after this event, and
 * deleting a term would otherwise move its children up a level instead.
 *
 * @see mukurtu_taxonomy_taxonomy_term_predelete()
 */
class TermMergeChildrenSubscriber implements EventSubscriberInterface {

  public function __construct(protected NestedTerms $nestedTerms) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // \Drupal\term_merge\TermMergeEventNames::TERMS_MERGED, by value, since
    // term_merge isn't a dependency of this module.
    return ['term_merge.terms_merged' => 'moveChildren'];
  }

  /**
   * Moves each merged term's children under the target term.
   *
   * The target and the other merged terms stay where they are, and so does
   * any child the target is nested under. Deleting the merged term then moves
   * those up a level.
   */
  public function moveChildren(TermsMergedEvent $event): void {
    $target = (int) $event->getTargetTerm()->id();
    $merged = array_map(fn ($term) => (int) $term->id(), $event->getSourceTerms());
    foreach ($merged as $tid) {
      $this->nestedTerms->moveChildren($tid, $target, [...$merged, $target]);
    }
  }

}
