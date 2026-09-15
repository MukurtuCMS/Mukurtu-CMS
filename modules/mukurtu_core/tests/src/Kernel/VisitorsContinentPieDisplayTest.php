<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Controller\VisitorsLocationReportController;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the contrib view config the Locations chart depends on.
 *
 * VisitorsLocationReportController embeds a display by name, and
 * mukurtu_core_views_pre_render() removes a footer handler by name. Both are
 * contrib config, not our own, so a rename upstream would take the chart off
 * the page or leave a broken toggle link behind with nothing failing. These
 * assertions turn that into a red test.
 *
 * Installing "visitors" for real (via ModuleInstallerInterface, not the
 * $modules property - see feedback_kerneltestbase_modules_no_real_install in
 * project memory) also brings in charts and charts_chartjs, which is what
 * views.view.visitors lists as its config dependencies.
 *
 * @see \Drupal\mukurtu_core\Controller\VisitorsLocationReportController
 * @see mukurtu_core_views_pre_render()
 */
#[Group('mukurtu_core')]
class VisitorsContinentPieDisplayTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'path', 'field', 'options'];

  /**
   * The displays of the "visitors" view.
   *
   * @var array
   */
  protected array $displays;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('module_installer')->install(['visitors']);

    $view = \Drupal::configFactory()->get('views.view.visitors');
    $this->assertNotNull($view->get('id'), 'Precondition: views.view.visitors did not install.');
    $this->displays = $view->get('display');
  }

  /**
   * The chart display the controller embeds still exists.
   */
  public function testContinentPieDisplayExists(): void {
    $this->assertArrayHasKey(
      VisitorsLocationReportController::CHART_DISPLAY_ID,
      $this->displays,
    );
    $this->assertSame(
      'embed',
      $this->displays[VisitorsLocationReportController::CHART_DISPLAY_ID]['display_plugin'],
      'The chart display has to stay an embed display; the report page renders it directly rather than routing to it.',
    );
  }

  /**
   * Both displays the hook strips a toggle link from still carry one.
   */
  public function testSuppressedDisplaysStillCarryADisplayLink(): void {
    foreach (VisitorsLocationReportController::SUPPRESSED_DISPLAYS as $display_id) {
      $this->assertArrayHasKey($display_id, $this->displays);
      $this->assertArrayHasKey(
        'visitors_display_link',
        $this->displays[$display_id]['display_options']['footer'] ?? [],
        sprintf('The %s display no longer has a toggle link, so the suppression hook is now a no-op.', $display_id),
      );
    }
  }

  /**
   * The continent table's toggle link points at the chart we render ourselves.
   *
   * This is what makes the link redundant on the Locations report, and so what
   * justifies removing it there.
   */
  public function testContinentTableLinksToTheChartDisplay(): void {
    $footer = $this->displays['continent_table']['display_options']['footer'];

    $this->assertSame(
      VisitorsLocationReportController::CHART_DISPLAY_ID,
      $footer['visitors_display_link']['display_id'],
    );
  }

  /**
   * The Language table's link points at a table, which is why it is kept.
   */
  public function testLanguageTableLinksToAnotherTable(): void {
    $footer = $this->displays['language_table']['display_options']['footer'];
    $target = $footer['visitors_display_link']['display_id'];

    $this->assertNotSame(VisitorsLocationReportController::CHART_DISPLAY_ID, $target);
    $this->assertArrayHasKey($target, $this->displays);
  }

}
