<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the continent pie chart to the /visitors/location report.
 *
 * Every display in views.view.visitors is an embed display, so which of them
 * appear on a report page is decided in PHP, not by Views. Upstream's
 * ReportController::location() embeds the table displays only; the pie chart
 * is reachable solely through a "chart" footer link that swaps the table out
 * for it over AJAX. Reviewers reasonably read that as the chart being broken,
 * since nothing that looks like a chart appears until you click.
 *
 * This renders the chart on page load instead, alongside - not instead of -
 * the Continent table. The two carry the same numbers, which is deliberate:
 * charts_chartjs draws into a <canvas>, and while it does wrap it in a
 * role="figure" with an accessible name, a canvas still conveys no data to a
 * screen reader. The table is the chart's text alternative, so it has to stay.
 *
 * This wraps upstream's controller rather than extending it. Extending would
 * mean naming a visitors class in an "extends" clause, which PHP resolves when
 * the file is loaded, so anything that autoloaded this class on a site without
 * the visitors module would fatal - and mukurtu_core deliberately does not
 * depend on visitors. Upstream's create() also builds "new self()", so a
 * subclass would have to re-declare create() and pin itself to a contrib
 * constructor signature. Resolving the delegate by name at request time avoids
 * both, and keeps the render-array work in plain static methods that unit
 * tests can call without a container.
 *
 * @see \Drupal\mukurtu_core\Routing\RouteSubscriber
 * @see mukurtu_core_views_pre_render()
 */
final class VisitorsLocationReportController implements ContainerInjectionInterface {

  /**
   * The route this controller takes over.
   */
  public const ROUTE_NAME = 'visitors.location';

  /**
   * The view the report is built from.
   */
  public const VIEW_ID = 'visitors';

  /**
   * The chart display added to the page.
   */
  public const CHART_DISPLAY_ID = 'continent_pie';

  /**
   * Displays whose chart/table toggle link is redundant on this route.
   *
   * Both the table and the chart are on screen, so the links no longer switch
   * between two views - they would duplicate one.
   *
   * @see mukurtu_core_views_pre_render()
   */
  public const SUPPRESSED_DISPLAYS = ['continent_table', 'continent_pie'];

  /**
   * The contrib controller whose output is being extended.
   */
  private const DELEGATE = '\Drupal\visitors\Controller\Report\ReportController';

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver, used to build the contrib controller.
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('class_resolver'));
  }

  /**
   * Builds the Locations report with the continent pie chart included.
   *
   * @return array
   *   A render array for the page.
   */
  public function location(): array {
    // getInstanceFromDefinition() calls the delegate's own create(), so
    // visitors keeps ownership of its constructor.
    $delegate = $this->classResolver->getInstanceFromDefinition(self::DELEGATE);

    return static::addChartRow($delegate->location());
  }

  /**
   * Inserts the chart row into the report build.
   *
   * @param array $build
   *   The render array returned by the contrib controller.
   *
   * @return array
   *   The render array with a chart row after the first report row.
   */
  public static function addChartRow(array $build): array {
    // Degrade to "no chart" rather than guess if upstream restructures the
    // report. The page still renders exactly as it does without this class.
    if (!isset($build['main']) || !is_array($build['main'])) {
      return $build;
    }

    $rows = Element::children($build['main']);
    if (empty($rows)) {
      return $build;
    }

    // Upstream keys its rows '1', '2', '3', which PHP stores as integer keys,
    // so a new string-keyed row appended here would sort to the bottom of the
    // page rather than into position. Give every row an explicit weight and
    // slot the chart in after the first one, which is the Continent/Country
    // pair it plots. Reading the rows back out of the render array instead of
    // hardcoding those keys keeps this working if upstream adds or renames a
    // row.
    $weight = 0;
    foreach ($rows as $row) {
      $build['main'][$row]['#weight'] = $weight++;

      if ($weight === 1) {
        $build['main']['continent_chart'] = [
          static::buildChartRow(),
          '#weight' => $weight++,
        ];
      }
    }

    // mukurtu_core_views_pre_render() strips the toggle links on this route
    // only, so the output must not be reused for a different one.
    $build['#cache']['contexts'][] = 'route';

    return $build;
  }

  /**
   * Whether a view's chart/table toggle link should be dropped.
   *
   * Kept here, next to SUPPRESSED_DISPLAYS, so the rule and the reason for it
   * live together; called from mukurtu_core_views_pre_render().
   *
   * @param string $route_name
   *   The current route.
   * @param string $view_id
   *   The view being rendered.
   * @param string $display_id
   *   The display being rendered.
   *
   * @return bool
   *   TRUE if the link is redundant and should be removed.
   */
  public static function suppressesDisplayLink(string $route_name, string $view_id, string $display_id): bool {
    return $route_name === self::ROUTE_NAME
      && $view_id === self::VIEW_ID
      && in_array($display_id, self::SUPPRESSED_DISPLAYS, TRUE);
  }

  /**
   * Builds the single-column report row holding the chart.
   *
   * Mirrors the shape of ReportBaseController::renderViews(), but builds the
   * '#type' => 'view' element directly: views_embed_view(), which that method
   * calls, is deprecated in Drupal 11.4, and building the element keeps this
   * method free of any container dependency.
   *
   * @return array
   *   A render array for one layout row.
   */
  protected static function buildChartRow(): array {
    return [
      // layout-row is upstream's row class, from visitors/css/report.css.
      '#prefix' => '<div class="layout-row">',
      'blocks' => [
        [
          '#type' => 'view',
          '#name' => self::VIEW_ID,
          '#display_id' => self::CHART_DISPLAY_ID,
          '#arguments' => [],
          '#attributes' => [
            'class' => [
              // Upstream's column class, so the chart picks up the same
              // width and stacking behaviour as the tables.
              'layout-column--half',
              // Ours, so the chart column can be styled without catching
              // the table columns beside it.
              'layout-column--chart',
            ],
          ],
        ],
      ],
      '#suffix' => '</div>',
    ];
  }

}
