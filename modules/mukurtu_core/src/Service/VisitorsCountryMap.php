<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\leaflet\LeafletService;

/**
 * Builds the visitor location map shown on the /visitors/location reports.
 *
 * The visitors report counts visitors per place in tables; this plots the
 * same numbers on a map. It reads the coordinates visitors_geoip already
 * stores per visit (location_latitude / location_longitude) and averages them
 * per city, which puts a circle roughly at the city without needing a
 * gazetteer.
 *
 * By default every city is plotted. A site can raise the city threshold so
 * that cities with fewer unique visitors are not plotted on their own: they
 * are combined with the rest of their region, and a region still under the
 * threshold with the rest of its country, so a city with one or two visitors
 * cannot be read off the map as "someone from here looked at this site".
 * Anything still under the threshold at country level is left off the map
 * entirely; the tables beside it still count it.
 *
 * The query deliberately mirrors the report's own views so the two cannot
 * disagree: same COUNT(DISTINCT visitor_id) measure, same bot exclusion, and
 * the same global date range the rest of the report obeys.
 *
 * Every visitors dependency is optional. mukurtu_core does not depend on the
 * visitors module, so with it uninstalled the services resolve to NULL and
 * this returns an empty render array rather than breaking the container.
 *
 * @see \Drupal\mukurtu_core\Controller\VisitorsLocationReportController
 */
class VisitorsCountryMap {

  use StringTranslationTrait;

  /**
   * Height of the rendered map.
   *
   * Tall enough to read a world map at the width of one report column.
   */
  protected const MAP_HEIGHT = '400px';

  /**
   * The Leaflet map definition to render with.
   *
   * The name leaflet ships its OpenStreetMap definition under. Looked up by
   * name rather than taken positionally so a site that adds its own map
   * definitions does not change which one this uses.
   */
  protected const MAP_ID = 'openstreetmap';

  /**
   * The DOM id given to the rendered map element.
   *
   * Fixed rather than auto-generated so mukurtu_core_preprocess_leaflet_map()
   * can tell this map apart from any other Leaflet map on the site.
   *
   * @see mukurtu_core_preprocess_leaflet_map()
   */
  public const MAP_ELEMENT_ID = 'visitors-country-map';

  /**
   * The config object holding the map settings.
   */
  public const CONFIG_NAME = 'mukurtu_core.visitors_map';

  /**
   * The city threshold used when the setting has never been saved.
   */
  public const DEFAULT_CITY_THRESHOLD = 1;

  /**
   * Marker diameters in CSS pixels, for the smallest and largest count.
   *
   * 24px is the WCAG 2.2 target size minimum (2.5.8), and every marker is a
   * focusable control, so nothing is drawn smaller than that.
   */
  protected const MIN_DIAMETER = 24;
  protected const MAX_DIAMETER = 48;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Leaflet render service.
   *
   * @var \Drupal\leaflet\LeafletService
   */
  protected $leaflet;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Resolves where the DB-IP database is stored.
   *
   * @var \Drupal\mukurtu_core\Service\DbIpDatabaseLocator
   */
  protected $dbIpLocator;

  /**
   * The visitors date range service, or NULL if visitors is not installed.
   *
   * @var \Drupal\visitors\VisitorsDateRangeInterface|null
   */
  protected $dateRange;

  /**
   * The visitors location service, or NULL if visitors is not installed.
   *
   * @var \Drupal\visitors\VisitorsLocationInterface|null
   */
  protected $location;

  /**
   * Constructs the map builder.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\leaflet\LeafletService $leaflet
   *   The Leaflet render service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\mukurtu_core\Service\DbIpDatabaseLocator $db_ip_locator
   *   Resolves where the DB-IP database is stored.
   * @param object|null $date_range
   *   The visitors.date_range service, if the visitors module is installed.
   * @param object|null $location
   *   The visitors.location service, if the visitors module is installed.
   */
  public function __construct(Connection $database, LeafletService $leaflet, TranslationInterface $string_translation, ConfigFactoryInterface $config_factory, DbIpDatabaseLocator $db_ip_locator, $date_range = NULL, $location = NULL) {
    $this->database = $database;
    $this->leaflet = $leaflet;
    $this->configFactory = $config_factory;
    $this->dbIpLocator = $db_ip_locator;
    $this->dateRange = $date_range;
    $this->location = $location;
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the map render array.
   *
   * @param string|null $country
   *   Limit the map to this country code, as on the country and region
   *   drill-down reports.
   * @param string|null $region
   *   Limit the map to this region of $country. '_none' means visits with no
   *   region, matching the drill-down routes.
   *
   * @return array
   *   A render array for the map, or an empty array if it cannot be built.
   */
  public function build(?string $country = NULL, ?string $region = NULL): array {
    if (!$this->dateRange || !$this->location) {
      return [];
    }

    // visitors_geoip is what adds the coordinate columns, and it can be
    // uninstalled independently of visitors. Bail out entirely rather than
    // fall through to the empty state below: with no coordinates anywhere,
    // "no location data for the selected dates" would blame the date filter
    // for something no date range can fix.
    if (!$this->database->schema()->fieldExists('visitors', 'location_latitude')) {
      return [];
    }

    $points = static::aggregate($this->getCityTotals($country, $region), $this->cityThreshold());
    $features = $this->buildFeatures($points);

    if (!$features) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['visitors-country-map', 'visitors-country-map--empty'],
          // Same role and name as the populated branch, so this card still
          // announces as the map rather than as a stray sentence.
          'role' => 'figure',
          'aria-label' => $this->t('Visitor locations'),
        ],
        'message' => ['#markup' => $this->t('No location data for the selected dates.')],
        '#cache' => $this->cacheability(),
      ];
    }

    // leaflet_map_get_info() returns every definition when called with no
    // argument, and leafletRenderMap() wants one of them. Passing the whole
    // set renders a div that Leaflet then silently declines to initialise.
    $definitions = leaflet_map_get_info();
    $map = $definitions[self::MAP_ID] ?? reset($definitions);
    if (!$map) {
      return [];
    }

    // Leaflet's own behaviour calls fitBounds() on load, so the view frames
    // whichever places are in range without a hardcoded centre or zoom.
    $map['id'] = self::MAP_ELEMENT_ID;

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['visitors-country-map']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Visitor map'),
        '#attributes' => [
          'id' => 'visitors-country-map-heading',
          'class' => ['visitors-country-map__heading'],
        ],
      ],
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Each circle is a city. Larger circles mean more unique visitors. The tables on this page list the same data.'),
        '#attributes' => [
          'id' => 'visitors-country-map-description',
          'class' => ['visitors-country-map__description'],
        ],
      ],
      // Every circle is its own tab stop, so a keyboard user who wants the
      // tables needs a way past them all.
      'skip' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this->t('Skip visitor map'),
        '#attributes' => [
          'href' => '#visitors-country-map-end',
          'class' => ['visually-hidden', 'focusable', 'visitors-country-map__skip'],
        ],
      ],
      'figure' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['visitors-country-map__figure'],
          // Mirrors how charts_chartjs exposes its canvas: a named figure, so
          // the map is announced rather than read as an unlabelled group. The
          // tables beside it carry the same numbers as text, which is what
          // keeps this accessible; the map itself is a visual summary.
          'role' => 'figure',
          'aria-labelledby' => 'visitors-country-map-heading',
          'aria-describedby' => 'visitors-country-map-description',
        ],
        'map' => $this->leaflet->leafletRenderMap($map, $features, self::MAP_HEIGHT),
      ],
      'end' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'id' => 'visitors-country-map-end',
          'tabindex' => '-1',
        ],
      ],
      '#cache' => $this->cacheability(),
    ];

    // DB-IP's CC BY 4.0 licence requires this credit wherever its data is
    // shown. Shown whenever its database is installed, since even with
    // MaxMind configured it still answers MaxMind's misses.
    if ($this->dbIpLocator->realpath()) {
      $build['attribution'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<a href=":url">IP Geolocation by DB-IP</a>', [':url' => 'https://db-ip.com']),
        '#attributes' => ['class' => ['visitors-country-map__attribution']],
      ];
    }

    return $build;
  }

  /**
   * The minimum unique visitors for a city to get its own circle.
   */
  public function cityThreshold(): int {
    $threshold = $this->configFactory->get(self::CONFIG_NAME)->get('city_threshold');
    return max(1, (int) ($threshold ?? self::DEFAULT_CITY_THRESHOLD));
  }

  /**
   * Cache metadata shared by the populated and empty map.
   */
  protected function cacheability(): array {
    return [
      'contexts' => ['visitors_date_range'],
      'tags' => ['config:' . self::CONFIG_NAME],
    ];
  }

  /**
   * Returns unique visitors and an average position per city.
   *
   * @param string|null $country
   *   Limit to this country code.
   * @param string|null $region
   *   Limit to this region of $country; '_none' for no region.
   *
   * @return array
   *   Rows with country, region, city, unique_visitors, lat and lng keys.
   */
  protected function getCityTotals(?string $country = NULL, ?string $region = NULL): array {
    $query = $this->database->select('visitors', 'v');
    $query->addField('v', 'location_country', 'country');
    $query->addField('v', 'location_region', 'region');
    $query->addField('v', 'location_city', 'city');
    $query->addExpression('COUNT(DISTINCT v.visitor_id)', 'unique_visitors');
    // A plain mean, not a circular one: for a place whose visits straddle
    // the antimeridian, averaging longitudes of +179 and -179 yields 0 and
    // drops the circle in the Atlantic. Accepted rather than solved, because
    // within one city the spread is far too small for that to happen, and
    // the tables beside the map are the authority on the numbers.
    $query->addExpression('AVG(v.location_latitude)', 'lat');
    $query->addExpression('AVG(v.location_longitude)', 'lng');
    // Matches the report tables' own filters: real visitors only, inside the
    // date range the filter form at the top of the report sets.
    $query->condition('v.bot', 1, '<>');
    $query->condition('v.visitors_date_time', [
      $this->dateRange->getStartTimestamp(),
      $this->dateRange->getEndTimestamp(),
    ], 'BETWEEN');
    $query->condition('v.location_country', '', '<>');
    $query->isNotNull('v.location_latitude');
    $query->isNotNull('v.location_longitude');
    if ($country) {
      $query->condition('v.location_country', $country);
      if ($region) {
        $query->condition('v.location_region', $region === '_none' ? '' : $region);
      }
    }
    $query->groupBy('v.location_country');
    $query->groupBy('v.location_region');
    $query->groupBy('v.location_city');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Combines cities under the threshold into their region, then country.
   *
   * A city at or over the threshold keeps its own point. The rest of each
   * region is pooled into one "other cities" point at the visit-weighted
   * centre of those cities; a pool still under the threshold moves up into
   * its country's pool, and a country pool still under it is dropped.
   *
   * Pooled counts add the per-city distinct counts, so a visitor seen in two
   * of the pooled cities is counted twice. That only ever makes a pool look
   * slightly larger than it is, never smaller, so it cannot lower a pool past
   * the threshold that is protecting it.
   *
   * @param array $rows
   *   Rows as returned by getCityTotals().
   * @param int $threshold
   *   The minimum unique visitors for a point of its own.
   *
   * @return array
   *   Points with country, region, city, other, unique_visitors, lat and lng
   *   keys. 'other' is TRUE for a pooled point, whose city is '' and whose
   *   region is '' when pooled at country level.
   */
  public static function aggregate(array $rows, int $threshold): array {
    $points = [];
    $regions = [];

    foreach ($rows as $row) {
      if (!isset($row['lat'], $row['lng'])) {
        continue;
      }
      $count = (int) ($row['unique_visitors'] ?? 0);
      $country = (string) ($row['country'] ?? '');
      $region = (string) ($row['region'] ?? '');
      $city = (string) ($row['city'] ?? '');

      if ($city !== '' && $count >= $threshold) {
        $points[] = [
          'country' => $country,
          'region' => $region,
          'city' => $city,
          'other' => FALSE,
          'unique_visitors' => $count,
          'lat' => (float) $row['lat'],
          'lng' => (float) $row['lng'],
        ];
        continue;
      }

      static::addToPool($regions[$country][$region], $count, (float) $row['lat'], (float) $row['lng']);
    }

    $countries = [];
    foreach ($regions as $country => $pools) {
      foreach ($pools as $region => $pool) {
        if ($region !== '' && $pool['count'] >= $threshold) {
          $points[] = static::poolPoint($pool, (string) $country, (string) $region);
          continue;
        }
        static::addToPool($countries[$country], $pool['count'], $pool['lat'] / $pool['count'], $pool['lng'] / $pool['count']);
      }
    }

    foreach ($countries as $country => $pool) {
      if ($pool['count'] >= $threshold) {
        $points[] = static::poolPoint($pool, (string) $country, '');
      }
    }

    return $points;
  }

  /**
   * Adds visits to a pool as count-weighted coordinate sums.
   */
  private static function addToPool(?array &$pool, int $count, float $lat, float $lng): void {
    $pool ??= ['count' => 0, 'lat' => 0.0, 'lng' => 0.0];
    $pool['count'] += $count;
    $pool['lat'] += $lat * $count;
    $pool['lng'] += $lng * $count;
  }

  /**
   * Turns a pool into an "other cities" point at its weighted centre.
   */
  private static function poolPoint(array $pool, string $country, string $region): array {
    return [
      'country' => $country,
      'region' => $region,
      'city' => '',
      'other' => TRUE,
      'unique_visitors' => $pool['count'],
      'lat' => $pool['lat'] / $pool['count'],
      'lng' => $pool['lng'] / $pool['count'],
    ];
  }

  /**
   * Turns map points into Leaflet point features.
   *
   * Public so it can be unit tested on its own; the database and Leaflet work
   * around it cannot be exercised without a full site.
   *
   * @param array $points
   *   Points as returned by aggregate(). A row with only a country, as
   *   getCityTotals() returned before the map went city-level, is labelled
   *   with the country alone.
   *
   * @return array
   *   Leaflet feature definitions.
   */
  public function buildFeatures(array $points): array {
    $features = [];
    $max = 1;
    foreach ($points as $point) {
      $max = max($max, (int) ($point['unique_visitors'] ?? 0));
    }

    foreach ($points as $point) {
      $lat = $point['lat'] ?? NULL;
      $lng = $point['lng'] ?? NULL;
      if ($lat === NULL || $lng === NULL) {
        continue;
      }

      $count = (int) ($point['unique_visitors'] ?? 0);
      // @place is escaped by t(), which matters twice over: place names come
      // from a third-party database, and both the tooltip and the marker's
      // HTML below are inserted as markup.
      $text = (string) $this->t('@place: @visitors', [
        '@place' => $this->placeLabel($point),
        // Not '@count': that is formatPlural()'s own reserved placeholder, and
        // reusing it in a non-plural t() confuses translators and extraction.
        '@visitors' => $this->formatPlural($count, '1 unique visitor', '@count unique visitors'),
      ]);

      // Area, not radius, tracks the count, so a city with four times the
      // visitors reads as four times the circle rather than sixteen.
      $diameter = (int) round(self::MIN_DIAMETER + (self::MAX_DIAMETER - self::MIN_DIAMETER) * sqrt($count / $max));

      // Tooltip only, deliberately no popup. Leaflet shows the tooltip on
      // focus as well as hover and wires it up with aria-describedby, and it
      // carries the same text a popup would. A popup adds a dialog whose
      // close button sits several tab stops away, behind every other marker,
      // and which Escape does not close while focus is still on the marker
      // that opened it. Nothing is lost by leaving it out.
      //
      // An 'html' (DivIcon) marker rather than leaflet's 'circle_marker':
      // a CircleMarker is an SVG path Leaflet does not make focusable, which
      // would leave keyboard users with no way to reach the tooltip. A
      // DivIcon is a real L.Marker - a focusable button - drawn as a circle
      // by CSS, and its visually hidden text is its accessible name.
      $features[] = [
        'type' => 'point',
        'lat' => (float) $lat,
        'lon' => (float) $lng,
        'tooltip' => ['value' => $text],
        'icon' => [
          'iconType' => 'html',
          'html' => '<span class="visitors-country-map__dot" aria-hidden="true"></span><span class="visually-hidden">' . $text . '</span>',
          'html_class' => 'visitors-country-map__marker',
          'iconSize' => ['x' => $diameter, 'y' => $diameter],
        ],
      ];
    }

    return $features;
  }

  /**
   * A point's place name: "City, Region, Country", or its pool's name.
   *
   * The pool name is returned as markup, not cast to a string, so the @place
   * placeholder it is passed into does not escape its already-escaped
   * region name a second time.
   */
  protected function placeLabel(array $point): string|\Stringable {
    $country = $this->location->getCountryLabel($point['country'] ?? '');
    $region = (string) ($point['region'] ?? '');

    if (!empty($point['other'])) {
      return $this->t('Other cities in @region', ['@region' => $region !== '' ? $region : $country]);
    }

    return implode(', ', array_filter([
      (string) ($point['city'] ?? ''),
      $region,
      $country,
    ], 'strlen'));
  }

}
