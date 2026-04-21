<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;

/**
 * Provides a logger that throws exceptions when logging errors.
 */
class TestLogger extends LoggerChannel implements LoggerChannelFactoryInterface {

  use RfcLoggerTrait;

  /**
   * The number of currently expected errors.
   *
   * @var int
   */
  protected $expectedErrors = 0;

  /**
   * Retrieves the number of currently expected logged errors.
   *
   * @return int
   *   The number of currently expected logged errors.
   */
  public function getExpectedErrors(): int {
    return $this->expectedErrors;
  }

  /**
   * Sets an expectation for one or more errors to be logged.
   *
   * @param int $num_expected_errors
   *   The new expected number of errors.
   * @param int|null $expected_previous_setting
   *   (optional) The expected previous setting of the number of expected errors
   *   in order to make an assertion on that. Or NULL to skip the assertion.
   *
   * @return $this
   */
  public function setExpectedErrors(int $num_expected_errors = 1, ?int $expected_previous_setting = 0): self {
    if ($expected_previous_setting !== NULL) {
      Assert::assertEquals($expected_previous_setting, $this->expectedErrors);
    }
    $this->expectedErrors = $num_expected_errors;
    return $this;
  }

  /**
   * Asserts that all expected errors were in fact encountered.
   *
   * In other words, asserts that the currently expected number of errors to be
   * logged is 0.
   *
   * @return $this
   */
  public function assertAllExpectedErrorsEncountered(): self {
    Assert::assertEquals(0, $this->expectedErrors);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if ($level < RfcLogLevel::INFO) {
      if ($this->expectedErrors > 0) {
        --$this->expectedErrors;
        return;
      }
      $message = strtr($message, $context);
      throw new \Exception($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($channel): LoggerChannelInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0): void {}

}
