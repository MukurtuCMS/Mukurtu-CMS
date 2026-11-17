<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which pages get the page--node templates (issue #2749).
 *
 * Core suggests page__node* for every path under /node, but
 * page--node.html.twig only suits node displays, which bring their own grid.
 * Other pages there, such as Found In, the delete form and the /node listing,
 * must fall back to page.html.twig, which puts their content on the page
 * grid. mukurtu_v4_theme_suggestions_page_alter() lives in the theme, so it
 * is called directly, as in HiddenFrontPageTitleTest.
 */
#[Group('mukurtu_core')]
class PageNodeSuggestionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  /**
   * Runs the theme's page suggestion alter for a route.
   */
  protected function alterSuggestions(string $route_name, array $suggestions): array {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route_name);
    $this->container->set('current_route_match', $route_match);

    mukurtu_v4_theme_suggestions_page_alter($suggestions, []);
    return $suggestions;
  }

  /**
   * Node display routes keep every page__node suggestion.
   */
  #[DataProvider('nodeDisplayRoutes')]
  public function testNodeDisplayRoutesKeepPageNode(string $route_name, array $suggestions): void {
    $this->assertSame($suggestions, $this->alterSuggestions($route_name, $suggestions));
  }

  /**
   * Data provider for testNodeDisplayRoutesKeepPageNode().
   */
  public static function nodeDisplayRoutes(): array {
    $node = ['page__node', 'page__node__%', 'page__node__1'];
    return [
      'canonical' => ['entity.node.canonical', $node],
      'latest version' => ['entity.node.latest_version', [...$node, 'page__node__latest']],
      'preview' => ['entity.node.preview', ['page__node', 'page__node__preview', 'page__node__preview__full']],
      'revision' => ['entity.node.revision', [...$node, 'page__node__revisions', 'page__node__revisions__2', 'page__node__revisions__2__view']],
      'Layout Builder' => ['layout_builder.overrides.node.view', [...$node, 'page__node__layout']],
      'multipage' => ['mukurtu_multipage_items.multipage_node_view', [...$node, 'page__node__multipage']],
    ];
  }

  /**
   * Other routes under /node lose every page__node suggestion, and only those.
   */
  #[DataProvider('otherNodeRoutes')]
  public function testOtherNodeRoutesDropPageNode(string $route_name, array $suggestions, array $expected): void {
    $this->assertSame($expected, $this->alterSuggestions($route_name, $suggestions));
  }

  /**
   * Data provider for testOtherNodeRoutesDropPageNode().
   */
  public static function otherNodeRoutes(): array {
    $node = ['page__node', 'page__node__%', 'page__node__1'];
    return [
      'Found In' => ['mukurtu_core.content_found_in', [...$node, 'page__node__found_in'], []],
      'Add to Collection' => ['mukurtu_collection.add_item_to_collection', [...$node, 'page__node__collection', 'page__node__collection__add'], []],
      'delete form' => ['entity.node.delete_form', [...$node, 'page__node__delete'], []],
      '/node listing' => ['view.frontpage.page_1', ['page__node'], []],
      'other suggestions kept' => ['entity.node.delete_form', ['page__front', ...$node, 'page__nodes'], ['page__front', 'page__nodes']],
    ];
  }

  /**
   * Pages outside /node are left alone.
   */
  public function testNonNodeRouteUnchanged(): void {
    $suggestions = ['page__user', 'page__user__%', 'page__user__1'];
    $this->assertSame($suggestions, $this->alterSuggestions('entity.user.canonical', $suggestions));
  }

  /**
   * The Local Contexts project directory suggestion still works.
   */
  public function testLocalContextsSuggestionKept(): void {
    $this->assertSame(
      ['page__local_contexts_site_project_directory'],
      $this->alterSuggestions('mukurtu_local_contexts.site_project_directory', []),
    );
  }

}
