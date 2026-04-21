<?php

namespace Drupal\search_api\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a gathering of plugin information event.
 */
final class GatheringPluginInfoEvent extends Event {

  /**
   * The plugin definitions.
   *
   * @var array<string, mixed>
   */
  protected $definitions;

  /**
   * Constructs a new class instance.
   *
   * @param array<string, mixed> $definitions
   *   The plugin definitions collected so far, keyed by plugin ID.
   */
  public function __construct(array &$definitions) {
    $this->definitions = &$definitions;
  }

  /**
   * Retrieves the plugin definitions collected so far.
   *
   * @return array<string, mixed>
   *   The plugin definitions collected so far, keyed by plugin ID.
   */
  public function &getDefinitions(): array {
    return $this->definitions;
  }

}
