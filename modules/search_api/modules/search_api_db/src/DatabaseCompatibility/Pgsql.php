<?php

declare(strict_types = 1);

namespace Drupal\search_api_db\DatabaseCompatibility;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Represents a PostgreSQL database.
 */
class Pgsql extends CaseSensitiveDatabase {

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The connection to the database.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliterator
   *   The transliteration service to use.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Connection $database,
    #[Autowire(service: 'transliteration')]
    TransliterationInterface $transliterator,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($database, $transliterator);

    $this->logger = $logger_factory->get('search_api_db');
  }

  /**
   * {@inheritdoc}
   */
  public function orderByRandom(SelectInterface $query): void {
    // Attempt to set the random seed, if set.
    $seed = $query->getMetaData('search_api_random_sort_seed');
    if (isset($seed) && is_numeric($seed)) {
      if (!is_float($seed) || abs($seed) > 1.0) {
        $seed /= 10000000000.0;
        // Make extra-sure we are in the right range.
        while (abs($seed) > 1.0) {
          $seed /= 2;
        }
        try {
          $this->database->query('SELECT SETSEED(?)', [$seed]);
        }
        catch (DatabaseException $e) {
          // Log as a warning, but ignore otherwise.
          Error::logException(
            $this->logger,
            $e,
            '%type while trying to set random seed for database query: @message in %function (line %line of %file).',
            level: LogLevel::WARNING,
          );
        }
      }
    }

    parent::orderByRandom($query);
  }

}
