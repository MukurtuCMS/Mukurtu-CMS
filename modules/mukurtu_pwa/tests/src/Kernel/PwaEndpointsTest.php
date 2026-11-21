<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_pwa\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the installable-site endpoints and the spike's result endpoints.
 */
#[Group('mukurtu_pwa')]
class PwaEndpointsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'mukurtu_api',
    'mukurtu_core',
    'mukurtu_design',
    'mukurtu_pwa',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    parent::setUpFilesystem();
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'mukurtu_pwa']);
    $this->installEntitySchema('user');
    $this->config('system.site')->set('name', 'Example Archive')->save();
    $this->config('mukurtu_design.settings')
      ->set('colors', ['brand_primary' => '#123456'])
      ->save();
    Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous'])->save();
    Role::create(['id' => RoleInterface::AUTHENTICATED_ID, 'label' => 'Authenticated'])->save();
    // Burn uid 1, which bypasses permission checks.
    User::create(['name' => 'admin', 'status' => 1])->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Sends a request as an account.
   */
  protected function send(Request $request, $account = NULL): Response {
    $this->container->get('current_user')->setAccount($account ?? new AnonymousUserSession());
    // One process serves every request here. Core's 403 page swaps a GET
    // context onto the router, which on a real site ends with the request.
    // Put the shared context back, or every later POST is matched as a GET.
    $context = $this->container->get('router.request_context');
    $context->fromRequest($request);
    $this->container->get('router.no_access_checks')->setContext($context);
    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * Creates a signed-in tester.
   */
  protected function tester(): User {
    $user = User::create(['name' => 'tester', 'status' => 1]);
    $user->save();
    return $user;
  }

  /**
   * The web app manifest describes an installable, branded site.
   */
  public function testManifest(): void {
    $response = $this->send(Request::create('/manifest.webmanifest'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/manifest+json', $response->headers->get('Content-Type'));
    $data = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('Example Archive', $data['name']);
    $this->assertSame('standalone', $data['display']);
    $this->assertSame('#123456', $data['theme_color']);
    $this->assertSame('/', $data['scope']);
    $this->assertSame(['192x192', '512x512', '512x512'], array_column($data['icons'], 'sizes'));
  }

  /**
   * Icons are square PNGs at the requested size, and only allowed sizes.
   */
  public function testIcons(): void {
    foreach ([180, 192, 512] as $size) {
      $response = $this->send(Request::create("/pwa-icon/$size"));
      $this->assertSame(200, $response->getStatusCode());
      $this->assertSame('image/png', $response->headers->get('Content-Type'));
      $info = getimagesizefromstring((string) $response->getContent());
      $this->assertSame([$size, $size], [$info[0], $info[1]]);
    }
    $this->assertSame(404, $this->send(Request::create('/pwa-icon/100'))->getStatusCode());
  }

  /**
   * The service worker can control the whole site and has its settings.
   */
  public function testServiceWorker(): void {
    $response = $this->send(Request::create('/pwa-sw.js'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
    $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));
    $script = (string) $response->getContent();
    $this->assertStringNotContainsString('__VERSION__', $script);
    $this->assertStringNotContainsString('__CONFIG__', $script);
    $this->assertMatchesRegularExpression('/const VERSION = "[0-9a-f]{12}";/', $script);
    $this->assertStringContainsString('"restrictedMaxAgeDays":14', $script);
    $this->assertStringContainsString('"offlinePages":["/pwa-spike/outbox","/pwa-spike/status"]', $script);

    // Changing a setting changes the version, so devices update the worker.
    $this->config('mukurtu_pwa.settings')->set('restricted_max_age_days', 3)->save();
    $changed = (string) $this->send(Request::create('/pwa-sw.js'))->getContent();
    $this->assertStringContainsString('"restrictedMaxAgeDays":3', $changed);
    preg_match('/const VERSION = "([0-9a-f]+)"/', $script, $before);
    preg_match('/const VERSION = "([0-9a-f]+)"/', $changed, $after);
    $this->assertNotSame($before[1], $after[1]);
  }

  /**
   * Anyone can load the offline page, so the worker can store it.
   */
  public function testOfflinePage(): void {
    $response = $this->send(Request::create('/offline'));
    $this->assertSame(200, $response->getStatusCode());
    $html = (string) $response->getContent();
    $this->assertStringContainsString('saved on this device. You can still open the items below.', $html);
    $this->assertStringContainsString('mukurtu-pwa-offline__list', $html);
  }

  /**
   * Builds an upload request.
   */
  protected function uploadRequest(string $clientId, string $title = 'Draft'): Request {
    $path = $this->siteDirectory . '/photo.jpg';
    file_put_contents($path, 'not really a photo');
    $photo = new UploadedFile($path, 'photo.jpg', 'image/jpeg', NULL, TRUE);
    return Request::create('/pwa-spike/outbox/upload', 'POST', [
      'client_id' => $clientId,
      'title' => $title,
      'created' => '1795000000',
    ], [], ['photo' => $photo]);
  }

  /**
   * Uploads need a signed-in person, valid input, and are idempotent.
   */
  public function testUpload(): void {
    $id = '9d9fcea7-f2a1-4698-bb7c-0ab6061f3d47';
    $this->assertSame(403, $this->send($this->uploadRequest($id))->getStatusCode());

    $tester = $this->tester();
    $this->assertSame(400, $this->send($this->uploadRequest('not-a-uuid'), $tester)->getStatusCode());
    $this->assertSame(400, $this->send($this->uploadRequest($id, ''), $tester)->getStatusCode());

    $first = json_decode((string) $this->send($this->uploadRequest($id), $tester)->getContent(), TRUE);
    $this->assertSame(['ok' => TRUE, 'duplicate' => FALSE], $first);
    $again = json_decode((string) $this->send($this->uploadRequest($id), $tester)->getContent(), TRUE);
    $this->assertSame(['ok' => TRUE, 'duplicate' => TRUE], $again);

    $received = $this->container->get('keyvalue')->get('mukurtu_pwa_spike')->get('received');
    $this->assertCount(1, $received);
    $this->assertSame((int) $tester->id(), $received[$id]['uid']);
    $this->assertFileExists("private://pwa-spike/$id.bin");
  }

  /**
   * Reports keep only known keys, and results are for administrators.
   */
  public function testReportsAndResults(): void {
    $report = Request::create('/pwa-spike/report', 'POST', [], [], [], [], json_encode([
      'deviceId' => '1b4e28ba-2fa1-41d2-883f-0016d3cca427',
      'itemsTotal' => 2,
      'itemsFound' => 2,
      'unexpected' => 'dropped',
    ]));
    $this->assertSame(403, $this->send(clone $report)->getStatusCode());
    $tester = $this->tester();
    $this->assertSame(200, $this->send(clone $report, $tester)->getStatusCode());

    $stored = $this->container->get('keyvalue')->get('mukurtu_pwa_spike')->get('reports');
    $this->assertCount(1, $stored);
    $this->assertArrayNotHasKey('unexpected', $stored[0]);
    $this->assertSame(2, $stored[0]['itemsFound']);

    $this->assertSame(403, $this->send(Request::create('/pwa-spike/results'), $tester)->getStatusCode());
  }

}
