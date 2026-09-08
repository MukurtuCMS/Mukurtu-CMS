<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests mukurtu_browse_preprocess_node()'s route-dependent behavior.
 */
#[Group('mukurtu_browse')]
class BrowseQuickActionsPreprocessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // mukurtu_browse itself is not enabled here: its declared dependency
    // chain (mukurtu_digital_heritage, search_api, facets, layout_builder,
    // paragraphs, blazy, geofield, leaflet) isn't needed to exercise this
    // one procedural hook. Load the .module file directly so
    // mukurtu_browse_preprocess_node() is defined.
    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_browse') . '/mukurtu_browse.module';

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Makes the given route name the current request's active route.
   */
  private function setCurrentRoute(string $route_name): void {
    $request = Request::create('/test');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/test'));
    // Mirror DrupalKernel::preHandle(), which attaches a session to the main
    // request. Without this, KernelTestBase::tearDown() finds this pushed
    // request still on top of the stack and throws SessionNotFoundException
    // when it tries to clean up its session.
    $request->setSession($this->container->get('session'));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * quick_actions must still be built on the digital-heritage browse route.
   *
   * Regression test: an earlier version of mukurtu_browse_preprocess_node()
   * returned early on this route - intended only to suppress the redundant
   * content_type_label - which also skipped building quick_actions,
   * dropping the "more actions" menu from every /digital-heritage card.
   */
  public function testQuickActionsBuiltOnDigitalHeritageRoute(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test node']);
    $node->save();

    $this->setCurrentRoute('mukurtu_browse.browse_digital_heritage_page');

    $variables = ['view_mode' => 'browse', 'node' => $node];
    mukurtu_browse_preprocess_node($variables);

    $this->assertArrayHasKey('quick_actions', $variables);
    $this->assertArrayNotHasKey('content_type_label', $variables);
  }

  /**
   * quick_actions and content_type_label are both built on /browse.
   */
  public function testQuickActionsAndLabelBuiltOnBrowseRoute(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test node']);
    $node->save();

    $this->setCurrentRoute('mukurtu_browse.browse_page');

    $variables = ['view_mode' => 'browse', 'node' => $node];
    mukurtu_browse_preprocess_node($variables);

    $this->assertArrayHasKey('quick_actions', $variables);
    $this->assertArrayHasKey('content_type_label', $variables);
    $this->assertSame('Page', (string) $variables['content_type_label']);
  }

  /**
   * Non-browse view modes are left untouched, on any route.
   */
  public function testNonBrowseViewModeSkipped(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Test node']);
    $node->save();

    $this->setCurrentRoute('mukurtu_browse.browse_page');

    $variables = ['view_mode' => 'full', 'node' => $node];
    mukurtu_browse_preprocess_node($variables);

    $this->assertArrayNotHasKey('quick_actions', $variables);
    $this->assertArrayNotHasKey('content_type_label', $variables);
  }

}
