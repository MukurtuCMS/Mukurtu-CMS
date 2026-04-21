<?php

namespace Drupal\search_api\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Display\DisplayInterface;

/**
 * Wraps an "is rendered in current request" event.
 */
final class IsRenderedInCurrentRequestEvent extends Event {

  /**
   * The search display.
   *
   * @var \Drupal\search_api\Display\DisplayInterface
   */
  protected $display;

  /**
   * Indicates if the search display is rendered in current request or not.
   *
   * @var bool
   */
  protected $rendered;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api\Display\DisplayInterface $display
   *   The search display.
   * @param bool $rendered
   *   TRUE if the search display is currently thought to be rendered in the
   *   current request, FALSE otherwise.
   */
  public function __construct(DisplayInterface $display, bool $rendered) {
    $this->display = $display;
    $this->rendered = $rendered;
  }

  /**
   * Retrieves the search display in question.
   *
   * @return \Drupal\search_api\Display\DisplayInterface
   *   The search display in question.
   */
  public function getDisplay(): DisplayInterface {
    return $this->display;
  }

  /**
   * Retrieves the current result of the detection process.
   *
   * @return bool
   *   TRUE if the search display is currently thought to be rendered in the
   *   current request, FALSE otherwise.
   */
  public function isRendered(): bool {
    return $this->rendered;
  }

  /**
   * Sets the result of the detection process.
   *
   * @param bool $rendered
   *   TRUE if the search display is currently thought to be rendered in the
   *   current request, FALSE otherwise.
   */
  public function setRendered(bool $rendered): void {
    $this->rendered = $rendered;
  }

}
