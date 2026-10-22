<?php

namespace Drupal\mukurtu_gin_custom\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Suppresses Layout Builder's "You have unsaved changes." warning.
 *
 * No other content editing page in Mukurtu shows an "unsaved changes"
 * warning, and reliably displaying this one turned out to require fighting
 * two independent, competing mechanisms that both draw from the same
 * Messenger queue: core's own auto-embedded status_messages on every AJAX
 * canvas rebuild (\Drupal\layout_builder\Element\LayoutBuilder::layout(),
 * which has no per-type filter and is toastified by gin_lb since it's a
 * genuine layout_builder.* route response), and any custom mechanism trying
 * to show it elsewhere (e.g. once an off-canvas dialog closes). Rather than
 * keep it and reconcile the two, it's suppressed entirely for consistency
 * with the rest of the editing experience -- this also keeps issue #1822
 * fixed (the warning leaking onto an unrelated later page), just by
 * discarding the message instead of displaying it correctly in context.
 *
 * \Drupal\layout_builder\EventSubscriber\PrepareLayout::onPrepareLayout()
 * (priority 10) is the only thing that ever queues this specific warning.
 * Running at a lower priority guarantees this executes in the same
 * PREPARE_LAYOUT dispatch, immediately after, before core's own rebuild
 * self-embed or anything else has a chance to display it. Only the exact
 * "You have unsaved changes." text is removed; any other warning queued by
 * something else is left alone.
 */
class LbSuppressUnsavedWarningSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new LbSuppressUnsavedWarningSubscriber.
   */
  public function __construct(
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::PREPARE_LAYOUT][] = ['onPrepareLayout', 0];
    return $events;
  }

  /**
   * Removes the "unsaved changes" warning core just queued, if present.
   */
  public function onPrepareLayout(PrepareLayoutEvent $event): void {
    $target = (string) $this->t('You have unsaved changes.');

    $warnings = $this->messenger->messagesByType(MessengerInterface::TYPE_WARNING);
    if (!$warnings) {
      return;
    }

    $this->messenger->deleteByType(MessengerInterface::TYPE_WARNING);
    foreach ($warnings as $warning) {
      if ((string) $warning !== $target) {
        $this->messenger->addWarning($warning, TRUE);
      }
    }
  }

}
