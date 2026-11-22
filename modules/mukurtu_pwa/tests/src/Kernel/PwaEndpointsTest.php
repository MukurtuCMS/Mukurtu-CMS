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
   * Reads the results file the way a Tugboat rebuild leaves it.
   */
  protected function results(): array {
    $path = 'private://pwa-spike/results.json';
    $this->assertFileExists($path);
    return json_decode((string) file_get_contents($path), TRUE);
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

    $received = $this->results()['received'];
    $this->assertCount(1, $received);
    $this->assertSame((int) $tester->id(), $received[$id]['uid']);
    $this->assertFileExists("private://pwa-spike/$id.bin");
  }

  /**
   * Builds a report request from one device.
   */
  protected function reportRequest(string $deviceId): Request {
    return Request::create('/pwa-spike/report', 'POST', [], [], [], [], json_encode([
      'deviceId' => $deviceId,
      'itemsTotal' => 2,
      'itemsFound' => 2,
      'unexpected' => 'dropped',
    ]));
  }

  /**
   * Reports keep only known keys, signed in or not, and all are kept.
   *
   * Signed-out reports matter because a Tugboat rebuild signs every phone out.
   */
  public function testReports(): void {
    $phone = '1b4e28ba-2fa1-41d2-883f-0016d3cca427';
    $other = '6f1c2d3e-4b5a-4c7d-8e9f-0a1b2c3d4e5f';
    $this->assertSame(400, $this->send(Request::create('/pwa-spike/report', 'POST', [], [], [], [], '{}'))->getStatusCode());
    $this->assertSame(200, $this->send($this->reportRequest($phone))->getStatusCode());
    $tester = $this->tester();
    $this->assertSame(200, $this->send($this->reportRequest($phone), $tester)->getStatusCode());
    $this->assertSame(200, $this->send($this->reportRequest($other), $tester)->getStatusCode());

    $reports = $this->results()['reports'];
    $this->assertCount(3, $reports);
    $this->assertSame([$phone, $phone, $other], array_column($reports, 'deviceId'));
    $this->assertSame([0, (int) $tester->id(), (int) $tester->id()], array_column($reports, 'uid'));
    $this->assertArrayNotHasKey('unexpected', $reports[0]);
    $this->assertSame(2, $reports[0]['itemsFound']);
  }

  /**
   * Results are for administrators, and come from the file.
   */
  public function testResults(): void {
    $this->send($this->reportRequest('1b4e28ba-2fa1-41d2-883f-0016d3cca427'));
    $tester = $this->tester();
    $this->assertSame(403, $this->send(Request::create('/pwa-spike/results'), $tester)->getStatusCode());

    Role::create(['id' => 'spike_admin', 'label' => 'Spike admin'])
      ->grantPermission('administer site configuration')
      ->save();
    $admin = User::create(['name' => 'spike-admin', 'status' => 1, 'roles' => ['spike_admin']]);
    $admin->save();
    $response = $this->send(Request::create('/pwa-spike/results'), $admin);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($this->results(), json_decode((string) $response->getContent(), TRUE));
  }

  /**
   * Every page links to the tester pages, which an installed app can't type.
   */
  public function testTesterBar(): void {
    $page_top = [];
    mukurtu_pwa_page_top($page_top);
    $html = (string) $this->container->get('renderer')->renderInIsolation($page_top);

    $this->assertStringContainsString('<nav class="mukurtu-pwa-tester-bar" aria-label="Offline testing">', $html);
    $this->assertStringContainsString('href="/pwa-spike/status">Status</a>', $html);
    $this->assertStringContainsString('href="/pwa-spike/outbox">Upload test</a>', $html);
    $this->assertStringContainsString('href="/browse?search_api_fulltext=Offline%20test">Test items</a>', $html);
  }

}
