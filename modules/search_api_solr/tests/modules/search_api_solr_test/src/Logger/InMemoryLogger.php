<?php

namespace Drupal\search_api_solr_test\Logger;

use Psr\Log\AbstractLogger;

/**
 * A simple in memory logger.
 */
class InMemoryLogger extends AbstractLogger {

  /**
   * The log messages.
   *
   * @var array
   */
  private $messages = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $this->messages[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
  }

  /**
   * Gets the last log message.
   */
  public function getLastMessage() {
    return end($this->messages);
  }

}
