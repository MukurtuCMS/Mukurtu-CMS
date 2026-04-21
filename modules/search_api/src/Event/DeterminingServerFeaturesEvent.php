<?php

namespace Drupal\search_api\Event;

use Drupal\search_api\ServerInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a determining server features event.
 */
final class DeterminingServerFeaturesEvent extends Event {

  /**
   * Reference to the features supported by the server's backend.
   *
   * @var string[]
   */
  protected $features;

  /**
   * The search server in question.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Constructs a new class instance.
   *
   * @param string[] $features
   *   Reference to the features supported by the server's backend.
   * @param \Drupal\search_api\ServerInterface $server
   *   The search server in question.
   */
  public function __construct(array &$features, ServerInterface $server) {
    $this->features = &$features;
    $this->server = $server;
  }

  /**
   * Retrieves a reference to the features supported by the server's backend.
   *
   * @return string[]
   *   Reference to the features supported by the server's backend.
   */
  public function &getFeatures(): array {
    return $this->features;
  }

  /**
   * Retrieves the search server in question.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The search server in question.
   */
  public function getServer(): ServerInterface {
    return $this->server;
  }

}
