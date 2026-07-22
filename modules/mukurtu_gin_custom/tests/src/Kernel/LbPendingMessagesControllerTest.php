<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\Controller\LbPendingMessagesController;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that queued Layout Builder warnings surface via AJAX on demand.
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
   * Tests that a queued warning is inserted, but a status message is not.
   */
  public function testBuildDrainsOnlyWarnings(): void {
    $messenger = \Drupal::messenger();
    $messenger->addStatus('The layout override has been saved.');
    $messenger->addWarning('You have unsaved changes.');

    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();
    $commands = $response->getCommands();

    $this->assertCount(1, $commands);
    $this->assertSame('insert', $commands[0]['command']);
    $this->assertSame('[data-drupal-messages]', $commands[0]['selector']);
    $this->assertStringContainsString('You have unsaved changes.', (string) $commands[0]['data']);
    $this->assertStringNotContainsString('The layout override has been saved.', (string) $commands[0]['data']);

    // The warning was drained; the status message is left queued for its
    // normal display point on the next full page render.
    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_WARNING));
    $this->assertNotEmpty($messenger->messagesByType(MessengerInterface::TYPE_STATUS));
  }

  /**
   * Tests that nothing is inserted when no warning is queued.
   */
  public function testBuildWithNoQueuedWarning(): void {
    \Drupal::messenger()->addStatus('The layout override has been saved.');

    $response = LbPendingMessagesController::create(\Drupal::getContainer())->build();

    $this->assertSame([], $response->getCommands());
  }

}
