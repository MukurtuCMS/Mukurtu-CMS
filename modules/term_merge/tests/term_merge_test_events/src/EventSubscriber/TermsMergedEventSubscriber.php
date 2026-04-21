<?php

namespace Drupal\term_merge_test_events\EventSubscriber;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\term_merge\TermsMergedEvent;
use Drupal\term_merge\TermMergeEventNames;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prints a message to the screen when some terms are merged.
 */
class TermsMergedEventSubscriber implements EventSubscriberInterface {

  use MessengerTrait;

  /**
   * Code that is executed when the event is triggered.
   */
  public function onTermMerge(TermsMergedEvent $event) {
    $this->messenger()->addMessage('The TermsMergedEvent was triggered.');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[TermMergeEventNames::TERMS_MERGED][] = ['onTermMerge'];
    return $events;
  }

}
