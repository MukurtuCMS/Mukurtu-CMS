<?php

namespace Drupal\mukurtu_local_contexts\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_local_contexts\Event\LocalContextsProjectReferenceUpdatedEvent;

/**
 * Mukurtu Local Contexts event subscriber.
 */
class MukurtuLocalContextsProjectReferenceUpdatedSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public function onProjectReferenceUpdated(LocalContextsProjectReferenceUpdatedEvent $event) {
    $project_id = $event->getProjectId();
    // Check if project cache exists, in which case exit.

    // Project does not exist. We need to request it from the hub outside of
    // the normal refresh cycle.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      LocalContextsProjectReferenceUpdatedEvent::EVENT_NAME => ['onProjectReferenceUpdated'],
    ];
  }

}
