<?php

namespace Drupal\mukurtu_search\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;


/**
 * Mukurtu Search event subscriber for SAPI DB index.
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
  public function defaultFieldIndex(FieldAvailableForIndexing $event) {
    $field_name = $event->field_definition->getName();
    $field_id = "{$event->entity_type_id}__{$field_name}";
    $label = $event->field_definition->getLabel();
    $indexes = ['mukurtu_browse_auto_index'];

    // Index text fields as full text.
    if (in_array($event->field_definition->getType(), ['string', 'string_long', 'text', 'text_long', 'text_with_summary'])) {
      // Disabled for the DB backend: "1118 Row size too large" and MySQL's
      // 64-index-per-table limit both apply. Solr does not have these limits.
      //$event->indexField('mukurtu_default_solr_index', $field_id, $field_name, $label);
    }

    // Cherry-picking the label field isn't ideal, but is our temporary
    // workaround for the row size too large issues.
    $entity_type_definition = \Drupal::entityTypeManager()->getDefinition($event->entity_type_id);
    $entityLabelFieldname = $entity_type_definition->getKey('label');
    if ($field_name == $entityLabelFieldname) {
      $event->indexField($indexes, $field_id, $field_name, $label);
    }

    // Date fields.
    if (in_array($event->field_definition->getType(), ['created', 'changed'])) {
      $event->indexField($indexes, $field_id, $field_name, $label, 'date');
    }

    // Communities computed field.
    if ($field_name == 'field_communities' && $event->field_definition->getType() == 'entity_reference') {
      $event->indexField($indexes, $field_id, 'field_communities:entity:name', $label, 'string');
    }

    // Taxonomy reference fields: only index the term name as fulltext (text).
    // String (facet) variants for common taxonomy fields are defined explicitly
    // in the YAML. Adding __name (string) dynamically was removed to avoid
    // exceeding MySQL's 64-index-per-table limit on sites with many content
    // types that each carry unique taxonomy reference fields (e.g. Place,
    // Person). Each dynamic addition here costs 1 key; adding __name would
    // cost 2, and the third subscriber (TaxonomyFieldSearchIndexSubscriber)
    // was also adding __uuid. That totalled 3 keys per field — exhausting the
    // available headroom above the ~41 static YAML entries.
    // @todo Add unit tests covering this branch.
    if ($event->field_definition->getType() == 'entity_reference' &&
        ($event->field_definition->getSetting('target_type') ?? '') == 'taxonomy_term') {
      $event->indexField($indexes, $field_id . "__name__text", "{$field_name}:entity:name", $label, 'text');
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
