<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\mukurtu_gin_custom\EventSubscriber\LbSuppressUnsavedWarningSubscriber;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the "unsaved changes" warning is discarded, others are not.
 *
 * @see \Drupal\mukurtu_gin_custom\EventSubscriber\LbSuppressUnsavedWarningSubscriber
 */
#[Group('mukurtu_gin_custom')]
class LbSuppressUnsavedWarningSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'layout_builder',
    'mukurtu_gin_custom',
  ];

  /**
   * Tests that only the exact "unsaved changes" text is removed.
   */
  public function testOnlyTargetWarningRemoved(): void {
    $messenger = \Drupal::messenger();
    $messenger->addWarning('You have unsaved changes.');
    $messenger->addWarning('Some other warning.');

    $event = new PrepareLayoutEvent($this->createMock(SectionStorageInterface::class));
    (new LbSuppressUnsavedWarningSubscriber($messenger))->onPrepareLayout($event);

    $remaining = array_map('strval', $messenger->messagesByType(MessengerInterface::TYPE_WARNING));
    $this->assertSame(['Some other warning.'], $remaining);
  }

  /**
   * Tests that nothing happens when no warning is queued.
   */
  public function testNoWarningsQueued(): void {
    $messenger = \Drupal::messenger();

    $event = new PrepareLayoutEvent($this->createMock(SectionStorageInterface::class));
    (new LbSuppressUnsavedWarningSubscriber($messenger))->onPrepareLayout($event);

    $this->assertSame([], $messenger->messagesByType(MessengerInterface::TYPE_WARNING));
  }

}
