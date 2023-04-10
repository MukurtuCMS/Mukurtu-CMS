<?php

namespace Drupal\mukurtu_taxonomy\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;

/**
 * Mukurtu Taxonomy event subscriber.
 */
class TaxonomyFieldSearchIndexSubscriber implements EventSubscriberInterface {

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

  /**
   * Field search indexing event handler.
   *
   * @param \Drupal\mukurtu_search\Event\FieldAvailableForIndexing $event
   *   Response event.
   */
  public function indexTaxonomyField(FieldAvailableForIndexing $event) {
    if ($event->field_definition->getType() == 'entity_reference' && $event->field_definition->getSetting('target_type') == 'taxonomy_term') {
      $field_name = $event->field_definition->getName();
      $field_id = "{$event->entity_type_id}__{$event->bundle}__{$field_name}__uuid";
      $property_path = "{$field_name}:entity:uuid";
      $label = "{$event->field_definition->getLabel()} » Taxonomy term » UUID";
      $event->indexField($field_id, $property_path, $label);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FieldAvailableForIndexing::NEW_FIELD => ['indexTaxonomyField'],
      FieldAvailableForIndexing::UPDATED_FIELD => ['indexTaxonomyField'],
    ];
  }

}
