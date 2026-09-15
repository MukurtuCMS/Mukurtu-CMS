<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Core\Render\Element;
use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Controller\VisitorsLocationReportController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the render-array work behind the /visitors/location chart.
 *
 * Unit rather than Kernel on purpose: mukurtu_core depends on ~30 modules
 * (blazy, leaflet, paragraphs, search_api, message_subscribe_ui and more), so
 * enabling it in a Kernel test to reach one render array is not worth the
 * cost. The controller keeps this logic in container-free static methods
 * precisely so it can be covered here instead.
 *
 * @see \Drupal\mukurtu_core\Controller\VisitorsLocationReportController
 */
#[Group('mukurtu_core')]
class VisitorsLocationReportControllerTest extends UnitTestCase {

  /**
   * A build shaped like the one ReportController::location() returns.
   *
   * Rows are keyed '1', '2', '3' by contrib, which PHP stores as integers -
   * the detail the weighting in addChartRow() exists to work around.
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
   * The chart row embeds the continent pie display.
   */
  public function testChartRowEmbedsTheContinentPieDisplay(): void {
    $build = VisitorsLocationReportController::addChartRow($this->contribBuild());

    $embed = $build['main']['continent_chart'][0]['blocks'][0];
    $this->assertSame('view', $embed['#type']);
    $this->assertSame('visitors', $embed['#name']);
    $this->assertSame('continent_pie', $embed['#display_id']);
    $this->assertContains('layout-column--half', $embed['#attributes']['class']);
    $this->assertContains('layout-column--chart', $embed['#attributes']['class']);
  }

  /**
   * The chart lands after the Continent/Country row, not at the end.
   *
   * This is the regression that matters: appending a string key to an array
   * whose other keys are integers puts the new row last, so the chart would
   * render below the Language and City tables instead of below the table it
   * plots.
   */
  public function testChartRowSortsDirectlyAfterTheFirstRow(): void {
    $build = VisitorsLocationReportController::addChartRow($this->contribBuild());

    $this->assertSame(
      ['1', 'continent_chart', '2', '3'],
      array_map('strval', Element::children($build['main'], TRUE)),
    );
  }

  /**
   * Contrib's own rows are untouched apart from the added weight.
   */
  public function testContribRowsAreOtherwiseUnchanged(): void {
    $before = $this->contribBuild();
    $after = VisitorsLocationReportController::addChartRow($before);

    foreach (['1', '2', '3'] as $row) {
      $this->assertArrayHasKey('#weight', $after['main'][$row]);
      unset($after['main'][$row]['#weight']);
      $this->assertSame($before['main'][$row], $after['main'][$row]);
    }
    $this->assertSame($before['visitors_date_filter_form'], $after['visitors_date_filter_form']);
  }

  /**
   * The route cache context is added, since the toggle links are route-scoped.
   */
  public function testRouteCacheContextIsAdded(): void {
    $build = VisitorsLocationReportController::addChartRow($this->contribBuild());

    $this->assertContains('route', $build['#cache']['contexts']);
  }

  /**
   * A build this code does not recognise is handed back untouched.
   *
   * If contrib restructures the report, the page should lose the chart, not
   * white-screen.
   */
  #[DataProvider('unrecognisedBuildProvider')]
  public function testUnrecognisedBuildIsReturnedUnchanged(array $build): void {
    $this->assertSame($build, VisitorsLocationReportController::addChartRow($build));
  }

  /**
   * Builds that addChartRow() must decline to touch.
   */
  public static function unrecognisedBuildProvider(): array {
    return [
      'no main container' => [['visitors_date_filter_form' => ['#markup' => 'x']]],
      'main is not an array' => [['main' => 'unexpected']],
      'main has no rows' => [['main' => ['#type' => 'container']]],
    ];
  }

  /**
   * Only the two redundant displays lose their toggle link.
   */
  #[DataProvider('displayLinkProvider')]
  public function testSuppressesDisplayLink(string $route, string $view, string $display, bool $expected): void {
    $this->assertSame(
      $expected,
      VisitorsLocationReportController::suppressesDisplayLink($route, $view, $display),
    );
  }

  /**
   * Cases for the toggle-link suppression rule.
   */
  public static function displayLinkProvider(): array {
    return [
      'continent table on the locations report' => ['visitors.location', 'visitors', 'continent_table', TRUE],
      'continent pie on the locations report' => ['visitors.location', 'visitors', 'continent_pie', TRUE],
      // The "Language Code" link points at another table, not a chart, so it
      // is still doing something and must survive.
      'language table keeps its link' => ['visitors.location', 'visitors', 'language_table', FALSE],
      // The AJAX endpoint the links themselves hit, and the drilldown page,
      // both still show a table on its own, so their toggles stay.
      'ajax report endpoint' => ['visitors.report', 'visitors', 'continent_table', FALSE],
      'continent drilldown page' => ['visitors.location.continent', 'visitors', 'continent_table', FALSE],
      'a different view' => ['visitors.location', 'visitors_geoip', 'region_table', FALSE],
    ];
  }

}
