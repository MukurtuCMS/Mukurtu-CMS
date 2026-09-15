<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Render\Element;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Controller\VisitorsLocationReportController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the render-array work behind the /visitors/location map.
 *
 * Unit rather than Kernel on purpose: mukurtu_core depends on ~30 modules
 * (blazy, leaflet, paragraphs, search_api, message_subscribe_ui and more), so
 * enabling it in a Kernel test to reach one render array is not worth the
 * cost. The controller keeps this logic in a container-free static method
 * precisely so it can be covered here instead.
 *
 * @see \Drupal\mukurtu_core\Controller\VisitorsLocationReportController
 */
#[Group('mukurtu_core')]
class VisitorsLocationReportControllerTest extends UnitTestCase {

  /**
   * A stand-in for the map render array.
   */
  private const MAP = ['#markup' => 'map'];

  /**
   * A build shaped like the one ReportController::location() returns.
   *
   * Rows are keyed '1', '2', '3' by contrib, which PHP stores as integers -
   * the detail the weighting in addMapRow() exists to work around.
   */
  private function contribBuild(): array {
    $row = fn(string $label): array => [
      ['#prefix' => '<div class="layout-row">', 'blocks' => [$label], '#suffix' => '</div>'],
    ];

    return [
      'visitors_date_filter_form' => ['#markup' => 'date filter'],
      'main' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['visitors-main']],
        '1' => $row('continent+country'),
        '2' => $row('distinct+region'),
        '3' => $row('language+city'),
      ],
      '#attached' => ['library' => ['visitors/visitors.report']],
    ];
  }

  /**
   * The map row carries the map and its own row modifier class.
   */
  public function testMapRowCarriesTheMap(): void {
    $build = VisitorsLocationReportController::addMapRow($this->contribBuild(), self::MAP);

    $row = $build['main']['country_map'][0];
    $this->assertSame([self::MAP], $row['blocks']);
    $this->assertStringContainsString('layout-row--map', $row['#prefix']);
  }

  /**
   * The map lands after the Continent/Country row, not at the end.
   *
   * This is the regression that matters: appending a string key to an array
   * whose other keys are integers puts the new row last, so the map would
   * render below the Language and City tables instead of below the countries
   * it plots.
   */
  public function testMapRowSortsDirectlyAfterTheFirstRow(): void {
    $build = VisitorsLocationReportController::addMapRow($this->contribBuild(), self::MAP);

    $this->assertSame(
      ['1', 'country_map', '2', '3'],
      array_map('strval', Element::children($build['main'], TRUE)),
    );
  }

  /**
   * Contrib's own rows are untouched apart from the added weight.
   */
  public function testContribRowsAreOtherwiseUnchanged(): void {
    $before = $this->contribBuild();
    $after = VisitorsLocationReportController::addMapRow($before, self::MAP);

    foreach (['1', '2', '3'] as $row) {
      $this->assertArrayHasKey('#weight', $after['main'][$row]);
      unset($after['main'][$row]['#weight']);
      $this->assertSame($before['main'][$row], $after['main'][$row]);
    }
    $this->assertSame($before['visitors_date_filter_form'], $after['visitors_date_filter_form']);
  }

  /**
   * With no map to show, the report is handed back exactly as contrib built it.
   *
   * VisitorsCountryMap returns an empty array when the visitors services are
   * missing, so this is the "visitors uninstalled" path.
   */
  public function testEmptyMapLeavesTheReportAlone(): void {
    $build = $this->contribBuild();

    $this->assertSame($build, VisitorsLocationReportController::addMapRow($build, []));
  }

  /**
   * A build this code does not recognise is handed back untouched.
   *
   * If contrib restructures the report, the page should lose the map, not
   * white-screen.
   */
  #[DataProvider('unrecognisedBuildProvider')]
  public function testUnrecognisedBuildIsReturnedUnchanged(array $build): void {
    $this->assertSame($build, VisitorsLocationReportController::addMapRow($build, self::MAP));
  }

  /**
   * Builds that addMapRow() must decline to touch.
   */
  public static function unrecognisedBuildProvider(): array {
    return [
      'no main container' => [['visitors_date_filter_form' => ['#markup' => 'x']]],
      'main is not an array' => [['main' => 'unexpected']],
      'main has no rows' => [['main' => ['#type' => 'container']]],
    ];
  }

}
