<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\leaflet\LeafletService;

/**
 * Builds the visitor country map shown on /visitors/location.
 *
 * The visitors report counts visitors per country in a table; this plots the
 * same numbers on a map. It reads the coordinates visitors_geoip already
 * stores per visit (location_latitude / location_longitude) and averages them
 * per country, which puts a marker roughly in the middle of the visits
 * recorded for that country without needing a country-centroid lookup table.
 *
 * The query deliberately mirrors the Country table's own view so the two
 * cannot disagree: same COUNT(DISTINCT visitor_id) measure, same bot
 * exclusion, and the same global date range the rest of the report obeys.
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
   * @param object|null $date_range
   *   The visitors.date_range service, if the visitors module is installed.
   * @param object|null $location
   *   The visitors.location service, if the visitors module is installed.
   */
  public function __construct(Connection $database, LeafletService $leaflet, TranslationInterface $string_translation, $date_range = NULL, $location = NULL) {
    $this->database = $database;
    $this->leaflet = $leaflet;
    $this->dateRange = $date_range;
    $this->location = $location;
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the map render array.
   *
   * @return array
   *   A render array for the map, or an empty array if it cannot be built.
   */
  public function build(): array {
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

    $features = $this->buildFeatures($this->getCountryTotals());

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
        '#cache' => ['contexts' => ['visitors_date_range']],
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
    // whichever countries are in range without a hardcoded centre or zoom.
    $map['id'] = self::MAP_ELEMENT_ID;
    $build = $this->leaflet->leafletRenderMap($map, $features, self::MAP_HEIGHT);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['visitors-country-map'],
        // Mirrors how charts_chartjs exposes its canvas: a named figure, so
        // the map is announced rather than read as an unlabelled group. The
        // Country table beside it carries the same numbers as text, which is
        // what keeps this accessible; the map itself is a visual summary.
        'role' => 'figure',
        'aria-label' => $this->t('Visitor locations'),
      ],
      'map' => $build,
      '#cache' => ['contexts' => ['visitors_date_range']],
    ];
  }

  /**
   * Returns unique visitors and an average position per country.
   *
   * @return array
   *   Rows with country, unique_visitors, lat and lng keys.
   */
  protected function getCountryTotals(): array {
    $query = $this->database->select('visitors', 'v');
    $query->addField('v', 'location_country', 'country');
    $query->addExpression('COUNT(DISTINCT v.visitor_id)', 'unique_visitors');
    // A plain mean, not a circular one: for a country whose visits straddle
    // the antimeridian (Fiji, Kiribati, and Russia or the US at their
    // extremes) averaging longitudes of +179 and -179 yields 0 and drops the
    // marker in the Atlantic. Accepted rather than solved, because the marker
    // is a visual summary and the Country table beside it is the authority on
    // the numbers; a wrong pin for Fiji misleads no one about the count.
    $query->addExpression('AVG(v.location_latitude)', 'lat');
    $query->addExpression('AVG(v.location_longitude)', 'lng');
    // Matches the Country table's own filters: real visitors only, inside the
    // date range the filter form at the top of the report sets.
    $query->condition('v.bot', 1, '<>');
    $query->condition('v.visitors_date_time', [
      $this->dateRange->getStartTimestamp(),
      $this->dateRange->getEndTimestamp(),
    ], 'BETWEEN');
    $query->condition('v.location_country', '', '<>');
    $query->isNotNull('v.location_latitude');
    $query->isNotNull('v.location_longitude');
    $query->groupBy('v.location_country');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Turns country totals into Leaflet point features.
   *
   * Public so it can be unit tested on its own; the database and Leaflet work
   * around it cannot be exercised without a full site.
   *
   * @param array $rows
   *   Rows as returned by getCountryTotals().
   *
   * @return array
   *   Leaflet feature definitions.
   */
  public function buildFeatures(array $rows): array {
    $features = [];

    foreach ($rows as $row) {
      $lat = $row['lat'] ?? NULL;
      $lng = $row['lng'] ?? NULL;
      if ($lat === NULL || $lng === NULL) {
        continue;
      }

      $count = (int) ($row['unique_visitors'] ?? 0);
      $label = $this->location->getCountryLabel($row['country'] ?? '');
      $text = $this->t('@country: @visitors', [
        '@country' => $label,
        // Not '@count': that is formatPlural()'s own reserved placeholder, and
        // reusing it in a non-plural t() confuses translators and extraction.
        '@visitors' => $this->formatPlural($count, '1 unique visitor', '@count unique visitors'),
      ]);

      // Tooltip only, deliberately no popup. Leaflet shows the tooltip on
      // focus as well as hover and wires it up with aria-describedby, and it
      // carries the same text a popup would. A popup adds a dialog whose
      // close button sits several tab stops away, behind every other marker,
      // and which Escape does not close while focus is still on the marker
      // that opened it. Nothing is lost by leaving it out.
      $features[] = [
        'type' => 'point',
        'lat' => (float) $lat,
        'lon' => (float) $lng,
        'tooltip' => ['value' => $text],
      ];
    }

    return $features;
  }

}
