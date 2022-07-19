<?php

namespace Drupal\mukurtu_core\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Event for related content field computation.
 */
class RelatedContentComputationEvent extends Event {

  const EVENT_NAME = 'mukurtu_core_related_content_computation';

  /**
   * The node having its related content computed.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $node;

  /**
   * The query being built.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  public $query;

  /**
   * The condition group specifically for related content conditions.
   *
   * @var \Drupal\Core\Entity\Query\ConditionInterface
   */
  public $relatedContentConditionGroup;

  /**
   * Constructs the object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node having its related content computed.
   */
  public function __construct(NodeInterface $node, QueryInterface $query) {
    $this->node = $node;
    $this->query = $query;
    $this->relatedContentConditionGroup = $query->orConditionGroup();
  }

}
