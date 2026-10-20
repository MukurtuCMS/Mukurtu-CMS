<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\Controller\LbPendingMessagesController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

/**
 * Tests that a queued "layout saved" status message surfaces via AJAX.
 *
 * @see \Drupal\mukurtu_gin_custom\Controller\LbPendingMessagesController
 */
#[Group('mukurtu_gin_custom')]
class LbPendingMessagesControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'mukurtu_gin_custom',
  ];

  /**
   * Tests that a queued status message is returned and drained.
   */
  public function testBuildDrainsStatuses(): void {
    $messenger = \Drupal::messenger();
    $messenger->addStatus('The layout override has been saved.');

    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();
    $data = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(['The layout override has been saved.'], $data['statuses']);
    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_STATUS));
  }

  /**
   * Tests that an empty list is returned when nothing is queued.
   */
  public function testBuildWithNothingQueued(): void {
    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();
    $data = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame([], $data['statuses']);
  }

  /**
   * Tests access for the request the JS actually sends.
   *
   * A POST with a session cookie and an X-CSRF-Token header, so the CSRF
   * check runs the same way it does in a browser.
   */
  #[DataProvider('providerRouteAccess')]
  public function testRouteAccess(?string $permission, ?string $token, bool $expected): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->willReturnCallback(
      fn ($candidate) => $candidate === $permission,
    );

    $request = $this->buildRequest('POST');
    if ($token === 'valid') {
      $token = $this->container->get('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
    }
    if ($token !== NULL) {
      $request->headers->set('X-CSRF-Token', $token);
    }

    $this->assertSame($expected, $this->container->get('access_manager')->checkRequest($request, $account));
  }

  /**
   * Data provider for testRouteAccess().
   */
  public static function providerRouteAccess(): array {
    return [
      'site administrator' => ['configure any layout', 'valid', TRUE],
      'Mukurtu Manager, basic pages' => ['configure editable page node layout overrides', 'valid', TRUE],
      'Mukurtu Manager, landing pages' => ['configure editable landing_page node layout overrides', 'valid', TRUE],
      'no Layout Builder permission' => [NULL, 'valid', FALSE],
      'missing token' => ['configure any layout', NULL, FALSE],
      'invalid token' => ['configure any layout', 'not-a-token', FALSE],
    ];
  }

  /**
   * Tests that GET is refused, since the endpoint drains the message queue.
   */
  public function testGetIsNotAllowed(): void {
    $this->expectException(MethodNotAllowedException::class);
    $this->buildRequest('GET');
  }

  /**
   * Builds a routed request to the endpoint with a session cookie.
   */
  private function buildRequest(string $method): Request {
    $request = Request::create('/mukurtu-gin-custom/layout-builder/pending-messages', $method);
    $session_name = $this->container->get('session_configuration')->getOptions($request)['name'];
    $request->cookies->set($session_name, 'test-session');
    // The router checks the method against its request context, not the
    // request it is given.
    $this->container->get('router.request_context')->fromRequest($request);
    $request->attributes->add($this->container->get('router.no_access_checks')->matchRequest($request));
    return $request;
  }

}
