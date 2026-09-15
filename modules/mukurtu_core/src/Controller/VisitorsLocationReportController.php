<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Element;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the visitor country map to the /visitors/location report.
 *
 * The Locations report is assembled in PHP rather than by Views: every display
 * in views.view.visitors is an embed display, and upstream's
 * ReportController::location() picks the table ones. Nothing on the page is
 * visual, which is what prompted the review note that the report "has no
 * chart" until you click the Continent table's "chart" link.
 *
 * Rather than promote that continent pie, this adds a map of the countries
 * visitors came from, which is the question the page is actually answering.
 * The pie stays reachable through its own toggle link for anyone who wants it.
 *
 * The tables stay: the map is a visual summary of numbers the Country table
 * already carries as text, and that table is what keeps the page accessible.
 *
 * This wraps upstream's controller rather than extending it. An "extends"
 * clause is resolved when the file loads, so anything that autoloaded this
 * class on a site without the visitors module would fatal, and mukurtu_core
 * does not depend on visitors. Upstream's create() also builds "new self()",
 * so a subclass would have to re-declare create() and pin itself to a contrib
 * constructor signature. Resolving the delegate by name at request time avoids
 * both, and keeps the render-array work in a static method that unit tests can
 * call without a container.
 *
 * @see \Drupal\mukurtu_core\Routing\RouteSubscriber
 * @see \Drupal\mukurtu_core\Service\VisitorsCountryMap
 */
final class VisitorsLocationReportController implements ContainerInjectionInterface {

  /**
   * The route this controller takes over.
   */
  public const ROUTE_NAME = 'visitors.location';

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
   * The country map builder.
   *
   * @var \Drupal\mukurtu_core\Service\VisitorsCountryMap
   */
  protected $countryMap;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver, used to build the contrib controller.
   * @param \Drupal\mukurtu_core\Service\VisitorsCountryMap $country_map
   *   The country map builder.
   */
  public function __construct(ClassResolverInterface $class_resolver, VisitorsCountryMap $country_map) {
    $this->classResolver = $class_resolver;
    $this->countryMap = $country_map;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('class_resolver'),
      $container->get('mukurtu_core.visitors_country_map')
    );
  }

  /**
   * Builds the Locations report with the country map included.
   *
   * @return array
   *   A render array for the page.
   */
  public function location(): array {
    // getInstanceFromDefinition() calls the delegate's own create(), so
    // visitors keeps ownership of its constructor.
    $delegate = $this->classResolver->getInstanceFromDefinition(self::DELEGATE);

    return static::addMapRow($delegate->location(), $this->countryMap->build());
  }

  /**
   * Inserts the map row into the report build.
   *
   * @param array $build
   *   The render array returned by the contrib controller.
   * @param array $map
   *   The map render array, or an empty array to leave the build alone.
   *
   * @return array
   *   The render array with a map row after the first report row.
   */
  public static function addMapRow(array $build, array $map): array {
    if (!$map) {
      return $build;
    }

    // Degrade to "no map" rather than guess if upstream restructures the
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
    // slot the map in after the first one, which is the Continent/Country pair
    // it plots. Reading the rows back out of the render array instead of
    // hardcoding those keys keeps this working if upstream adds or renames a
    // row.
    $weight = 0;
    foreach ($rows as $row) {
      $build['main'][$row]['#weight'] = $weight++;

      if ($weight === 1) {
        $build['main']['country_map'] = [
          [
            // layout-row is upstream's row class, from visitors/css/report.css.
            '#prefix' => '<div class="layout-row layout-row--map">',
            'blocks' => [$map],
            '#suffix' => '</div>',
          ],
          '#weight' => $weight++,
        ];
      }
    }

    return $build;
  }

}
