<?php

namespace Drupal\mukurtu_dictionary\EventSubscriber;

use Drupal\mukurtu_core\Event\RepresentativeMediaComputationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers dictionary media fields as representative media sources.
 */
class RepresentativeMediaComputationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RepresentativeMediaComputationEvent::EVENT_NAME => 'onRepresentativeMediaComputation',
    ];
  }

  /**
   * Adds dictionary media fields as candidate representative media sources.
   */
  public function onRepresentativeMediaComputation(RepresentativeMediaComputationEvent $event) {
    $event->addSourceField('field_thumbnail', -50);
    $event->addSourceField('field_recording', -30);
    $event->addSourceField('field_word_list_image', -10);
  }

}
