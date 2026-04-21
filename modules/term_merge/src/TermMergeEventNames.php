<?php

namespace Drupal\term_merge;

/**
 * Contains all events thrown while handling term merges.
 */
final class TermMergeEventNames {

  /**
   * Name of event triggered when one or more terms are merged into another.
   *
   * This event allows modules to react to a term merge. The event listener
   * method receives a \Drupal\term_merge\TermsMergedEvent instance.
   *
   * @Event
   *
   * @see \Drupal\term_merge\TermsMergedEvent
   *
   * @var string
   */
  const TERMS_MERGED = 'term_merge.terms_merged';

}
