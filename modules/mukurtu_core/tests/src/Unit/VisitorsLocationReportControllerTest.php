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
    $embed = fn(string $display): array => [
      '#type' => 'view',
      '#name' => 'visitors',
      '#display_id' => $display,
      '#arguments' => [],
    ];
    $row = fn(array $displays): array => [
      [
        '#prefix' => '<div class="layout-row">',
        'blocks' => array_map($embed, $displays),
        '#suffix' => '</div>',
      ],
    ];

    return [
      'visitors_date_filter_form' => ['#markup' => 'date filter'],
      'main' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['visitors-main']],
        '1' => $row(['continent_table', 'country_table']),
        '2' => $row(['distinct_countries_list', 'region_table']),
        '3' => $row(['language_table', 'city_table']),
      ],
      '#attached' => ['library' => ['visitors/visitors.report']],
    ];
  }

  /**
   * Returns the display ids embedded in a build, row by row.
   */
  private function displayIds(array $build): array {
    $out = [];
    foreach (Element::children($build['main'], TRUE) as $row) {
      foreach (Element::children($build['main'][$row]) as $index) {
        foreach ($build['main'][$row][$index]['blocks'] ?? [] as $block) {
          $out[] = $block['#display_id'] ?? '(not a view)';
        }
      }
    }
    return $out;
  }

  /**
   * The redundant distinct-countries card is dropped.
   */
  public function testRemoveDisplayDropsTheRequestedDisplay(): void {
    $build = VisitorsLocationReportController::removeDisplay($this->contribBuild(), 'distinct_countries_list');

    $this->assertSame(
      ['continent_table', 'country_table', 'region_table', 'language_table', 'city_table'],
      $this->displayIds($build),
    );
  }

  /**
   * Its row survives, so the table left behind keeps its place in the grid.
   */
  public function testRemoveDisplayKeepsARowThatStillHasBlocks(): void {
    $build = VisitorsLocationReportController::removeDisplay($this->contribBuild(), 'distinct_countries_list');

    $this->assertArrayHasKey('2', $build['main']);
    $this->assertCount(1, $build['main']['2'][0]['blocks']);
  }

  /**
   * A row emptied by the removal is dropped rather than left behind.
   */
  public function testRemoveDisplayDropsAnEmptiedRow(): void {
    $build = $this->contribBuild();
    $build['main']['4'] = [
      [
        '#prefix' => '<div class="layout-row">',
        'blocks' => [['#type' => 'view', '#name' => 'visitors', '#display_id' => 'lonely_table']],
        '#suffix' => '</div>',
      ],
    ];

    $build = VisitorsLocationReportController::removeDisplay($build, 'lonely_table');

    $this->assertArrayNotHasKey('4', $build['main']);
  }

  /**
   * A display that is not on the page leaves the build alone.
   */
  public function testRemoveDisplayIgnoresAnAbsentDisplay(): void {
    $before = $this->contribBuild();

    $this->assertSame($before, VisitorsLocationReportController::removeDisplay($before, 'not_on_this_page'));
  }

  /**
   * A display id belonging to another view is left alone.
   */
  public function testRemoveDisplayOnlyMatchesItsOwnView(): void {
    $before = $this->contribBuild();
    $before['main']['2'][0]['blocks'][0]['#name'] = 'visitors_geoip';

    $after = VisitorsLocationReportController::removeDisplay($before, 'distinct_countries_list');

    $this->assertContains('distinct_countries_list', $this->displayIds($after));
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
