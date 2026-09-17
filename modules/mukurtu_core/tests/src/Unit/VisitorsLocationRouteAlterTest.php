<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\mukurtu_core\Controller\VisitorsLocationReportController;
use Drupal\mukurtu_core\Routing\RouteSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests that only /visitors/location is repointed at the Mukurtu controller.
 *
 * @see \Drupal\mukurtu_core\Routing\RouteSubscriber::alterRoutes()
 */
#[Group('mukurtu_core')]
class VisitorsLocationRouteAlterTest extends UnitTestCase {

  private const CONTRIB = '\Drupal\visitors\Controller\Report\ReportController';

  /**
   * Builds a collection of the visitors report routes.
   */
  private function visitorsRouteCollection(): RouteCollection {
    $collection = new RouteCollection();

    foreach ([
      'visitors.location' => '/visitors/location',
      'visitors.software' => '/visitors/software',
      'visitors.location.continent' => '/visitors/location/continent/{continent}',
    ] as $name => $path) {
      $route = new Route($path);
      $route->setDefault('_controller', self::CONTRIB . '::location');
      $collection->add($name, $route);
    }

    return $collection;
  }

  /**
   * Runs the subscriber over a collection.
   */
  private function alter(RouteCollection $collection): void {
    $subscriber = new RouteSubscriber();
    (new \ReflectionMethod($subscriber, 'alterRoutes'))->invoke($subscriber, $collection);
  }

  /**
   * The Locations report is handled by the Mukurtu controller.
   */
  public function testLocationRouteUsesTheMukurtuController(): void {
    $collection = $this->visitorsRouteCollection();
    $this->alter($collection);

    $this->assertSame(
      VisitorsLocationReportController::class . '::location',
      $collection->get('visitors.location')->getDefault('_controller'),
    );
  }

  /**
   * Sibling report routes keep contrib's controller.
   *
   * Guards against widening this to every route whose name starts with
   * "visitors." - the other reports have no chart to auto-render, and the
   * continent drilldown shows a single table by design.
   */
  #[DataProvider('untouchedRouteProvider')]
  public function testSiblingReportRoutesAreLeftAlone(string $routeName): void {
    $collection = $this->visitorsRouteCollection();
    $this->alter($collection);

    $this->assertSame(
      self::CONTRIB . '::location',
      $collection->get($routeName)->getDefault('_controller'),
    );
  }

  /**
   * Routes the subscriber must not rewrite.
   */
  public static function untouchedRouteProvider(): array {
    return [
      'software report' => ['visitors.software'],
      'continent drilldown' => ['visitors.location.continent'],
    ];
  }

  /**
   * With the visitors module off there is no route, and nothing happens.
   *
   * This absence is the only guard the controller has: nothing autoloads it,
   * so it never resolves its contrib delegate on a site without visitors.
   */
  public function testMissingVisitorsRouteIsHarmless(): void {
    $collection = new RouteCollection();
    $this->alter($collection);

    $this->assertNull($collection->get('visitors.location'));
  }

}
