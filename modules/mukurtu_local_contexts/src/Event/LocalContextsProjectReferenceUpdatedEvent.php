<?php

namespace Drupal\mukurtu_local_contexts\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event when Local Contexts project is being updated in a field or other context.
 */
class LocalContextsProjectReferenceUpdatedEvent extends Event {
  const EVENT_NAME = 'mukurtu_local_contexts_project_refererence_updated';

  /**
   * The project IDs.
   *
   * @var array
   */
  protected $project_id;

  public function __construct($project_id) {
    $this->project_id = $project_id;
  }

  public function getProjectId() {
    return $this->project_id;
  }

}
