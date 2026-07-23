<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
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

}
