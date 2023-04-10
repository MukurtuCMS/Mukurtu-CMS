<?php

namespace Drupal\mukurtu_search\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;


/**
 * Mukurtu Search event subscriber.
 */
class BaseFieldsSearchIndexSubscriber implements EventSubscriberInterface {

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
  public function indexFullTextField(FieldAvailableForIndexing $event) {
    // Index text fields as full text.
    if (in_array($event->field_definition->getType(), ['string', 'string_long', 'text', 'text_long', 'text_with_summary'])) {
      $field_name = $event->field_definition->getName();
      $field_id = "{$event->entity_type_id}__{$field_name}";
      $label = $event->field_definition->getLabel();
      $event->indexField($field_id, $field_name, $label);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FieldAvailableForIndexing::NEW_FIELD => ['indexFullTextField'],
      FieldAvailableForIndexing::UPDATED_FIELD => ['indexFullTextField'],
    ];
  }

}
