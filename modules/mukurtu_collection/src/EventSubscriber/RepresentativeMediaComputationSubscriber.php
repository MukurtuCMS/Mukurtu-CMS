<?php

namespace Drupal\mukurtu_collection\EventSubscriber;

use Drupal\mukurtu_core\Event\RepresentativeMediaComputationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the collection image field as a representative media source.
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
   * Adds field_collection_image as a candidate representative media source.
   */
  public function onRepresentativeMediaComputation(RepresentativeMediaComputationEvent $event) {
    $event->addSourceField('field_collection_image', -20);
  }

}
