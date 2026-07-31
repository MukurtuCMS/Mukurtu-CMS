<?php

namespace Drupal\mukurtu_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Event for representative media field computation.
 */
class RepresentativeMediaComputationEvent extends Event {

  const EVENT_NAME = 'mukurtu_core_representative_media_computation';

  /**
   * The node having its representative media computed.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $node;

  /**
   * Candidate source fields, keyed by field name, value is the weight.
   *
   * @var array
   */
  protected $sourceFields = [];

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node having its representative media computed.
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * Registers a candidate media source field.
   *
   * Lower weights are given priority over higher weights, so that a single
   * entity with several populated candidate fields resolves to the same
   * field every time regardless of which module's subscriber ran first.
   *
   * @param string $field_name
   *   The entity reference field name to consider as a media source.
   * @param int $weight
   *   The field's priority relative to other candidates.
   */
  public function addSourceField(string $field_name, int $weight = 0): void {
    $this->sourceFields[] = ['field' => $field_name, 'weight' => $weight];
  }

  /**
   * Gets the candidate source fields in priority order.
   *
   * @return string[]
   *   The candidate field names, sorted by weight, without duplicates.
   */
  public function getSourceFields(): array {
    $fields = $this->sourceFields;
    usort($fields, function (array $a, array $b) {
      return $a['weight'] <=> $b['weight'];
    });
    return array_values(array_unique(array_column($fields, 'field')));
  }

}
