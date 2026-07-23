<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\Controller\LbPendingMessagesController;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that queued Layout Builder messages surface via AJAX on demand.
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
   * Tests that queued warnings and statuses are both returned and drained.
   */
  public function testBuildDrainsWarningsAndStatuses(): void {
    $messenger = \Drupal::messenger();
    $messenger->addStatus('The layout override has been saved.');
    $messenger->addWarning('You have unsaved changes.');

    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();
    $data = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame(['You have unsaved changes.'], $data['warnings']);
    $this->assertSame(['The layout override has been saved.'], $data['statuses']);

    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_WARNING));
    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_STATUS));
  }

  /**
   * Tests that empty lists are returned when nothing is queued.
   */
  public function testBuildWithNothingQueued(): void {
    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();
    $data = json_decode((string) $response->getContent(), TRUE);

    $this->assertSame([], $data['warnings']);
    $this->assertSame([], $data['statuses']);
  }

}
