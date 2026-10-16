<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Controller\MukurtuManageContentController;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that Mukurtu adds content at core's /node/add paths (#2399).
 *
 * Mukurtu used to route its own /admin/node/add and /admin/node/add/{type} to
 * the same form as core's /node/add. Those paths now redirect to core's, every
 * Mukurtu link points at core's routes, and core's picker uses Mukurtu's.
 */
#[Group('mukurtu_core')]
class NodeAddRouteTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    // The first user created is uid 1, which bypasses access checks. Use it up
    // so the tests below run as ordinary accounts.
    $this->createUser();
  }

  /**
   * Sends a GET request through the HTTP kernel.
   */
  protected function get(string $path) {
    return $this->container->get('http_kernel')->handle(Request::create($path));
  }

  /**
   * /admin/node/add redirects to /node/add.
   */
  public function testAddPageRedirects(): void {
    $this->setCurrentUser($this->createUser(['access content', 'create page content', 'create article content']));

    $response = $this->get('/admin/node/add');

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/node/add', $response->headers->get('Location'));
  }

  /**
   * /admin/node/add/{type} redirects to /node/add/{type}, keeping the query.
   */
  public function testAddFormRedirectsWithQuery(): void {
    $this->setCurrentUser($this->createUser(['access content', 'create page content']));

    $response = $this->get('/admin/node/add/page?foo=bar');

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/node/add/page?foo=bar', $response->headers->get('Location'));
  }

  /**
   * A ?destination= is passed on to the form instead of replacing the target.
   */
  public function testDestinationDoesNotSkipTheForm(): void {
    $this->setCurrentUser($this->createUser(['access content', 'create page content']));

    $response = $this->get('/admin/node/add/page?destination=/somewhere');

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/node/add/page?destination=/somewhere', urldecode($response->headers->get('Location')));
  }

  /**
   * An unknown content type is not found, not redirected.
   */
  public function testUnknownTypeIsNotFound(): void {
    $this->setCurrentUser($this->createUser(['access content', 'create page content']));

    $response = $this->get('/admin/node/add/no_such_type');

    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Users who can't add content are denied, not redirected.
   */
  public function testAnonymousUserIsDenied(): void {
    $response = $this->get('/admin/node/add/page');

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Core's /node/add uses Mukurtu's content type picker.
   */
  public function testCorePickerUsesMukurtuController(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('node.add_page');
    $this->assertSame(MukurtuManageContentController::class . '::addPage', $route->getDefault('_controller'));

    $this->setCurrentUser($this->createUser(['access content', 'create page content', 'create article content']));
    $build = MukurtuManageContentController::create($this->container)->addPage();
    $this->assertSame('mukurtu_node_add_list', $build['#theme']);
    $this->assertSame(['article', 'page'], array_keys($build['#content']));
  }

  /**
   * With one content type available, /node/add goes straight to its form.
   */
  public function testCorePickerSkipsToTheOnlyType(): void {
    $this->setCurrentUser($this->createUser(['access content', 'create page content']));

    $response = $this->get('/node/add');

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/node/add/page', $response->headers->get('Location'));
  }

  /**
   * Every Mukurtu "Add" menu and action link uses core's node add routes.
   */
  public function testLinksUseCoreRoutes(): void {
    $root = \Drupal::root();
    $profile = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $files = array_merge(
      glob("$root/$profile/modules/*/*.links.menu.yml"),
      glob("$root/$profile/modules/*/*.links.action.yml"),
    );
    $this->assertNotEmpty($files);

    $add_links = [];
    foreach ($files as $file) {
      foreach (Yaml::decode(file_get_contents($file)) ?? [] as $id => $link) {
        $route_name = $link['route_name'] ?? NULL;
        $this->assertNotContains($route_name, ['mukurtu_core.add', 'mukurtu_core.add_page'], "$id in " . basename($file) . ' uses an old add route.');
        if (in_array($route_name, ['node.add', 'node.add_page'], TRUE)) {
          $add_links[$id] = $route_name;
        }
      }
    }

    $this->assertSame('node.add_page', $add_links['mukurtu_core.add_content'] ?? NULL);
    foreach ([
      'mukurtu_core.create_basic_page_dashboard',
      'mukurtu_collection.add_collection',
      'mukurtu_dictionary.add_dictionary_word',
      'mukurtu_dictionary.add_dictionary_word_list',
    ] as $id) {
      $this->assertSame('node.add', $add_links[$id] ?? NULL, "$id uses node.add.");
    }
  }

}
