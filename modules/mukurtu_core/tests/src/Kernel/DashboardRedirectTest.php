<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the bare /dashboard path redirects to the Mukurtu dashboard.
 *
 * The dashboards module only routes /dashboard/{dashboard}, so /dashboard on
 * its own used to 404 (#2391).
 */
#[Group('mukurtu_core')]
class DashboardRedirectTest extends KernelTestBase {

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
    $this->installSchema('system', ['sequences']);
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
   * An authenticated user is sent to /dashboard/mukurtu_dashboard.
   */
  public function testAuthenticatedUserIsRedirected(): void {
    $this->setCurrentUser($this->createUser());

    $response = $this->get('/dashboard');

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/dashboard/mukurtu_dashboard', $response->headers->get('Location'));
  }

  /**
   * Anonymous users are denied, the same as on the dashboard itself.
   */
  public function testAnonymousUserIsDenied(): void {
    $response = $this->get('/dashboard');

    $this->assertSame(403, $response->getStatusCode());
  }

}
