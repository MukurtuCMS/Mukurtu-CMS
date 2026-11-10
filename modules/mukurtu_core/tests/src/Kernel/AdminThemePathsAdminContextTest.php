<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Routing\AdminThemePathsAdminContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests that empty Admin Theme path lists don't turn off the admin theme.
 *
 * Admin Theme checks its path lists with core's request_path condition, which
 * treats an empty list as matching every page. An empty "Exclude" list then
 * marked every page as not an admin page, and the whole site rendered admin
 * pages in the front-end theme.
 */
#[Group('mukurtu_core')]
class AdminThemePathsAdminContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_theme',
    'dashboards',
    'field',
    'file',
    'geofield',
    'image',
    'layout_builder',
    'layout_discovery',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * The placeholder path Admin Theme ships in both lists.
   */
  protected const PLACEHOLDER = '/dummy-path-needed-until-core-issue-2930364-is-fixed';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installConfig(['admin_theme']);
  }

  /**
   * Tests that the router uses Mukurtu's version of Admin Theme's context.
   */
  public function testServiceIsReplaced(): void {
    $this->assertInstanceOf(AdminThemePathsAdminContext::class, $this->container->get('router.admin_context'));
  }

  /**
   * Tests which pages count as admin pages for given path lists.
   *
   * @param string $include
   *   The "Include" path list.
   * @param string $exclude
   *   The "Exclude" path list.
   * @param string $path
   *   The path of the current request.
   * @param bool $admin_route
   *   Whether the route itself is flagged as an admin route.
   * @param bool $expected
   *   Whether the page is expected to count as an admin page.
   */
  #[DataProvider('providerIsAdminRoute')]
  public function testIsAdminRoute(string $include, string $exclude, string $path, bool $admin_route, bool $expected): void {
    $this->config('admin_theme.settings')
      ->set('paths', $include)
      ->set('exclude_paths', $exclude)
      ->save();

    $request_stack = $this->container->get('request_stack');
    $request = Request::create($path);
    $request->setSession($request_stack->getCurrentRequest()->getSession());
    $request_stack->push($request);
    $route = new Route($path, [], [], ['_admin_route' => $admin_route]);

    $this->assertSame($expected, $this->container->get('router.admin_context')->isAdminRoute($route));
  }

  /**
   * Data provider for testIsAdminRoute().
   */
  public static function providerIsAdminRoute(): array {
    return [
      'empty lists keep admin routes as admin' => ['', '', '/admin/appearance', TRUE, TRUE],
      'empty lists keep other routes as non-admin' => ['', '', '/node/1', FALSE, FALSE],
      'whitespace-only lists count as empty' => [" \r\n", " \r\n", '/admin/appearance', TRUE, TRUE],
      'shipped placeholders keep admin routes as admin' => [
        self::PLACEHOLDER,
        self::PLACEHOLDER,
        '/admin/appearance',
        TRUE,
        TRUE,
      ],
      'excluded path is not admin' => ['', '/admin/appearance', '/admin/appearance', TRUE, FALSE],
      'excluded wildcard is not admin' => ['', '/admin/*', '/admin/appearance', TRUE, FALSE],
      'included path is admin' => ['/group/roles/add', '', '/group/roles/add', FALSE, TRUE],
      'exclude wins over include' => ['/admin/appearance', '/admin/appearance', '/admin/appearance', FALSE, FALSE],
      'unlisted path uses the route flag' => ['/group/roles/add', '/user/*', '/node/1/edit', TRUE, TRUE],
    ];
  }

}
