<?php

namespace Drupal\mukurtu_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Event for determining why auto-discovered content matched a record.
 *
 * Dispatched only for the auto-discovered subset of a record's
 * field_all_related_content (i.e. results not already present in
 * field_related_content), so subscribers can report back, per candidate
 * node, which taxonomy vocabularies (if any) matched. Unlike
 * RelatedContentComputationEvent, which builds a database query, this event
 * inspects already-loaded candidate nodes directly, since a query's OR
 * conditions can't tell us after the fact which branch matched.
 */
class RelatedContentProvenanceEvent extends Event {

  const EVENT_NAME = 'mukurtu_core_related_content_provenance';

  /**
   * The record (e.g. Person or Place) having its related content examined.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $record;

  /**
   * The auto-discovered candidate nodes, keyed by node ID.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  public $candidates;

  /**
   * Provenance results, keyed by candidate node ID.
   *
   * Each value is an array:
   *   - vocabularies: array of taxonomy vocabulary machine names matched via
   *     a term reference field.
   *   - other: bool, TRUE if the candidate matched only via a direct
   *     node reference or an embedded UUID, with no vocabulary match.
   *
   * @var array
   */
  public $provenance = [];

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $record
   *   The record having its related content examined.
   * @param \Drupal\node\NodeInterface[] $candidates
   *   The auto-discovered candidate nodes, keyed by node ID.
   */
  public function __construct(NodeInterface $record, array $candidates) {
    $this->record = $record;
    $this->candidates = $candidates;
  }

}
