<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Drupal\mukurtu_core\Event\RepresentativeMediaComputationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the shared field_media_assets field as a representative media source.
 *
 * field_media_assets is defined identically by several content-type modules
 * as a generic media gallery field, so it is registered here once rather
 * than by each of those modules individually.
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
   * Adds field_media_assets as a candidate representative media source.
   */
  public function onRepresentativeMediaComputation(RepresentativeMediaComputationEvent $event) {
    $event->addSourceField('field_media_assets', -40);
  }

}
