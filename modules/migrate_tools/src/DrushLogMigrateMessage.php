<?php

declare(strict_types=1);

namespace Drupal\migrate_tools;

use Drupal\migrate\MigrateMessageInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Logger implementation for drush.
 *
 * @package Drupal\migrate_tools
 */
class DrushLogMigrateMessage implements MigrateMessageInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The map between migrate status and drush log levels.
   */
  protected array $map = [
    'status' => 'notice',
  ];

  public function __construct(LoggerInterface $logger) {
    $this->setLogger($logger);
  }

  /**
   * Output a message from the migration.
   *
   * @param string $message
   *   The message to display.
   * @param string $type
   *   The type of message to display.
   *
   * @see drush_log()
   */
  public function display($message, $type = 'status'): void {
    $type = $this->map[$type] ?? $type;
    $this->logger->log($type, $message);
  }

}
