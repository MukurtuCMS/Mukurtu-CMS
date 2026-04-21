<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Component\Datetime\Time;

/**
 * Provides a dummy time service that can be used for testing.
 */
class TestTimeService extends Time {

  /**
   * The request time to return.
   *
   * @var int
   *
   * @see \Drupal\Tests\search_api\Kernel\TestTimeService::getRequestTime()
   */
  protected int $requestTime;

  /**
   * Constructs a TestTimeService object.
   */
  public function __construct() {
    $this->requestTime = time();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return $this->requestTime;
  }

  /**
   * Sets the reported request time.
   *
   * @param int $request_time
   *   The new request time to report.
   *
   * @return $this
   */
  public function setRequestTime(int $request_time): static {
    $this->requestTime = $request_time;
    return $this;
  }

  /**
   * Advances the reported request time.
   *
   * @param int $seconds
   *   (optional) Number of seconds by which to advance the reported request
   *   time.
   *
   * @return $this
   */
  public function advanceTime($seconds = 1) {
    $this->requestTime += $seconds;
    return $this;
  }

}
