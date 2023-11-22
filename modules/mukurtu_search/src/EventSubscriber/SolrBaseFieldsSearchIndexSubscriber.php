<?php

namespace Drupal\mukurtu_search\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;


/**
 * Mukurtu Search event subscriber for the SAPI Solr backend.
 */
class SolrBaseFieldsSearchIndexSubscriber implements EventSubscriberInterface {

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
  public function defaultFieldIndex(FieldAvailableForIndexing $event) {
    $field_name = $event->field_definition->getName();
    $field_id = "{$event->entity_type_id}__{$field_name}";
    $label = $event->field_definition->getLabel();
    $indexes = ['mukurtu_default_solr_index'];

    //$event->entity_type_id == 'node' &&
    // Index text fields as full text.
    if (in_array($event->field_definition->getType(), ['string', 'string_long', 'text', 'text_long', 'text_with_summary']) && !in_array($field_name, ['revision_log','revision_log_message'])) {
      $event->indexField($indexes, $field_id, $field_name, $label);
    }

    // Cherry-pick the label field, make sure we get it.
    $entity_type_definition = \Drupal::entityTypeManager()->getDefinition($event->entity_type_id);
    $entityLabelFieldname = $entity_type_definition->getKey('label');
    if ($field_name == $entityLabelFieldname) {
      $event->indexField($indexes, $field_id, $field_name, $label, 'text', 4.0);
    }

    // Date fields.
    if (in_array($event->field_definition->getType(), ['created', 'changed'])) {
      $event->indexField($indexes, $field_id, $field_name, $label, 'date');
    }

    // Communities computed field.
    if ($field_name == 'field_communities' && $event->field_definition->getType() == 'entity_reference') {
      $event->indexField($indexes, $field_id, 'field_communities:entity:name', $label, 'string');
    }

    // Entity Reference fields.
    if ($event->field_definition->getType() == 'entity_reference') {
      if ($reference_entity_type_id = $event->field_definition->getSetting('target_type') ?? NULL) {
        if ($referencedEntityType = \Drupal::entityTypeManager()->getDefinition($reference_entity_type_id)) {
          if ($referencedLabelKey = $referencedEntityType->getKey('label')) {
            $event->indexField($indexes, $field_id . "__{$referencedLabelKey}", "{$field_name}:entity:{$referencedLabelKey}", $label, 'text');

            // For taxonomy terms, also index as string for easy faceting.
            if ($reference_entity_type_id == 'taxonomy_term') {
              $event->indexField($indexes, $field_id . "__{$referencedLabelKey}__facet", "{$field_name}:entity:{$referencedLabelKey}", "$label " . t("Facet"), 'string');
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      FieldAvailableForIndexing::NEW_FIELD => ['defaultFieldIndex'],
      FieldAvailableForIndexing::UPDATED_FIELD => ['defaultFieldIndex'],
    ];
  }

}
