<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;

/**
 * Geolocates visits recorded before a geolocation database was available.
 *
 * Until a site has a geolocation database (DB-IP's, downloaded automatically,
 * or MaxMind's), and on sites that recorded visits while the DB-IP fallback's
 * null-region bug was dropping every geolocated one, the visits that were
 * saved have only a country guessed from the browser's language, and no
 * region, city or coordinates. visitors_geoip can re-resolve them from their
 * stored IP addresses (RebuildLocationService), but only when an admin runs
 * its rebuild form or drush command by hand. This runs it on cron instead,
 * once, as soon as a database is available.
 *
 * Walks the visitors table by id with a cursor, rather than queueing every
 * address up front or asking RebuildLocationService::getLocations() for the
 * addresses still missing coordinates: that list never empties, because an
 * address no database can place (any private address, for one) keeps its
 * NULL coordinates forever. With a cursor each row is looked at once, and the
 * work ends at the highest id that existed when it started; visits recorded
 * after that are geolocated as they are tracked.
 *
 * @see \Drupal\visitors_geoip\Service\RebuildLocationService
 * @see \Drupal\mukurtu_core\Hook\GeoIpFallbackHooks::cron()
 */
class VisitorsLocationBackfill {

  /**
   * The state key holding progress: ['cursor' => id, 'max' => id] or 'done'.
   */
  public const STATE_KEY = 'mukurtu_core.visitors_location_backfill';

  /**
   * Rows read per query.
   */
  protected const BATCH_SIZE = 500;

  /**
   * Seconds of work allowed per cron run.
   *
   * Each address is one geolocation lookup and one indexed UPDATE, so this
   * covers thousands of addresses a run without holding up the rest of cron.
   */
  public const TIME_LIMIT = 20;

  /**
   * Cache tags of everything that reports visit locations.
   *
   * The report pages are cacheable for any date range that has already
   * ended (see visitors' DateRangeCacheContext), which is fine while past
   * visits never change, but the backfill changes them. The views' own tag
   * caching knows nothing of the visitors table, so their config tags are
   * the only handle on their cached results.
   */
  public const CACHE_TAGS = [
    VisitorsCountryMap::CACHE_TAG,
    'config:views.view.visitors',
    'config:views.view.visitors_geoip',
  ];

  /**
   * Constructs the backfill service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator.
   * @param object|null $rebuild
   *   The visitors_geoip.rebuild.location service, if visitors_geoip is
   *   installed. Untyped because mukurtu_core does not depend on it.
   * @param object|null $lookup
   *   The visitors_geoip.lookup service, if visitors_geoip is installed.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly StateInterface $state,
    protected readonly TimeInterface $time,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly ?object $rebuild = NULL,
    protected readonly ?object $lookup = NULL,
  ) {}

  /**
   * Starts the backfill, once, when a geolocation database is available.
   *
   * @return bool
   *   TRUE if this call started it.
   */
  public function startIfReady(): bool {
    if ($this->state->get(self::STATE_KEY) !== NULL || !$this->rebuild || !$this->lookup) {
      return FALSE;
    }
    if ($this->databaseMetadata() === NULL || !$this->database->schema()->fieldExists('visitors', 'location_latitude')) {
      return FALSE;
    }

    $max = $this->database->query('SELECT MAX(visitors_id) FROM {visitors}')->fetchField();
    $this->state->set(self::STATE_KEY, $max ? ['cursor' => 0, 'max' => (int) $max] : 'done');
    return TRUE;
  }

  /**
   * The active geolocation database's metadata, or NULL if there is none.
   *
   * @return object|null
   *   A \MaxMind\Db\Reader\Metadata object, or NULL.
   */
  public function databaseMetadata(): ?object {
    return $this->lookup?->metadata();
  }

  /**
   * Geolocates the next visits, until done or out of time.
   *
   * @param int $time_limit
   *   Seconds of work allowed.
   */
  public function process(int $time_limit = self::TIME_LIMIT): void {
    $progress = $this->state->get(self::STATE_KEY);
    if (!is_array($progress) || !$this->rebuild) {
      return;
    }

    $deadline = $this->time->getCurrentMicroTime() + $time_limit;
    try {
      $this->processUntil($progress, $deadline);
    }
    finally {
      // Once per run that did any work, even one cut short by an exception
      // part way through a batch, whose rows may already have changed.
      $this->cacheTagsInvalidator->invalidateTags(self::CACHE_TAGS);
    }
  }

  /**
   * Works through batches from $progress until done or past $deadline.
   */
  protected function processUntil(array $progress, float $deadline): void {
    do {
      $rows = $this->database->select('visitors', 'v')
        ->fields('v', ['visitors_id', 'visitors_ip'])
        ->condition('v.visitors_id', $progress['cursor'], '>')
        ->condition('v.visitors_id', $progress['max'], '<=')
        ->isNull('v.location_latitude')
        ->orderBy('v.visitors_id')
        ->range(0, static::BATCH_SIZE)
        ->execute()
        ->fetchAllKeyed();

      if (!$rows) {
        $this->state->set(self::STATE_KEY, 'done');
        return;
      }

      // One rebuild per address: it updates every row with that address
      // still missing coordinates, not just the rows in this batch.
      foreach (array_unique(array_filter($rows, 'strlen')) as $ip) {
        $this->rebuild->rebuild($ip);
      }

      $progress['cursor'] = (int) array_key_last($rows);
      $this->state->set(self::STATE_KEY, $progress);
    } while ($this->time->getCurrentMicroTime() < $deadline);
  }

  /**
   * Distinct addresses still to be looked at, or NULL when not running.
   */
  public function remaining(): ?int {
    $progress = $this->state->get(self::STATE_KEY);
    if (!is_array($progress)) {
      return NULL;
    }

    return (int) $this->database->select('visitors', 'v')
      ->fields('v', ['visitors_ip'])
      ->condition('v.visitors_id', $progress['cursor'], '>')
      ->condition('v.visitors_id', $progress['max'], '<=')
      ->isNull('v.location_latitude')
      ->condition('v.visitors_ip', '', '<>')
      ->distinct()
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
