<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_landing_page\Kernel;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme's guaranteed hidden <h1> on the front page (issue #2176).
 *
 * The front page hides the real page-title block in favor of a Layout
 * Builder hero block acting as the visual title, which otherwise leaves the
 * page with zero <h1>s (or multiple, if several hero blocks are placed -
 * see #2176/#2177). mukurtu_v4_preprocess_page() lives in the theme (not a
 * module), so it's exercised directly here rather than through the full
 * render pipeline, mirroring
 * modules/mukurtu_media/tests/src/Kernel/DocumentThumbnailAltTextTest.php.
 *
 * Deliberately keyed on is_front, not the landing_page bundle: the title
 * block's visibility condition is path-based (<front> only - its /landing/*
 * pattern never actually matches, see pathauto.pattern.landing_pages.yml).
 * A bundle-based check would add a second, duplicate <h1> to any
 * non-front-page landing_page node - and DefaultLandingPage's own
 * "second call creates a new node" behavior (see DefaultLandingPageTest)
 * means those routinely exist.
 */
#[Group('mukurtu_landing_page')]
class HiddenFrontPageTitleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'filter',
    'user',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node']);

    NodeType::create(['type' => 'landing_page', 'name' => 'Landing Page'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  /**
   * Stubs path.matcher::isFrontPage() and the current route's 'node'.
   */
  protected function setCurrentPage(bool $is_front, ?NodeInterface $node): void {
    $path_matcher = $this->createMock(PathMatcherInterface::class);
    $path_matcher->method('isFrontPage')->willReturn($is_front);
    $this->container->set('path.matcher', $path_matcher);

    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('node')->willReturn($node);
    $this->container->set('current_route_match', $route_match);
  }

  /**
   * A node on the front page gets a hidden title variable for page.html.twig.
   *
   * Bundle-agnostic on purpose - see the class docblock.
   */
  public function testFrontPageNodeGetsHiddenTitle(): void {
    $node = Node::create(['type' => 'landing_page', 'title' => 'My Landing Page', 'status' => TRUE]);
    $node->save();
    $this->setCurrentPage(TRUE, $node);

    $variables = [];
    mukurtu_v4_preprocess_page($variables);

    $this->assertSame('My Landing Page', $variables['hidden_front_page_title'] ?? NULL);
  }

  /**
   * A landing_page node that is NOT the front page gets no hidden title.
   *
   * Its own page-title block is not hidden there, so adding one would
   * produce a duplicate <h1> - this is the regression the is_front check
   * (rather than a bundle check) exists to prevent.
   */
  public function testNonFrontPageLandingPageGetsNoHiddenTitle(): void {
    $node = Node::create(['type' => 'landing_page', 'title' => 'An orphaned landing page', 'status' => TRUE]);
    $node->save();
    $this->setCurrentPage(FALSE, $node);

    $variables = [];
    mukurtu_v4_preprocess_page($variables);

    $this->assertArrayNotHasKey('hidden_front_page_title', $variables);
  }

  /**
   * A front page with no node parameter (e.g. a view) is a no-op.
   */
  public function testFrontPageWithNoNodeGetsNoHiddenTitle(): void {
    $this->setCurrentPage(TRUE, NULL);

    $variables = [];
    mukurtu_v4_preprocess_page($variables);

    $this->assertArrayNotHasKey('hidden_front_page_title', $variables);
  }

}
