<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\Controller\LbPendingMessagesController;
use PHPUnit\Framework\Attributes\Group;

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
   * Tests that the route itself requires the "configure any layout"
   * permission, not just an authenticated session.
   */
  public function testRouteRequiresLayoutBuilderPermission(): void {
    $access_manager = \Drupal::service('access_manager');

    $without_permission = $this->createMock(AccountInterface::class);
    $without_permission->method('hasPermission')->willReturn(FALSE);
    $without_permission->method('isAuthenticated')->willReturn(TRUE);

    $with_permission = $this->createMock(AccountInterface::class);
    $with_permission->method('hasPermission')->willReturnCallback(
      fn ($permission) => $permission === 'configure any layout',
    );
    $with_permission->method('isAuthenticated')->willReturn(TRUE);

    $this->assertFalse($access_manager->checkNamedRoute('mukurtu_gin_custom.lb_pending_messages', [], $without_permission));
    $this->assertTrue($access_manager->checkNamedRoute('mukurtu_gin_custom.lb_pending_messages', [], $with_permission));
  }

}
