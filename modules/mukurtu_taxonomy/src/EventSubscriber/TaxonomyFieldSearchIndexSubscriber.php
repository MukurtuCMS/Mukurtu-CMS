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
   * Indexes a per-field `__uuid` variant for every taxonomy reference field so
   * canonical taxonomy term pages can browse the content that references them.
   *
   * Solr only: the `search_api_db` browse index (`mukurtu_browse_auto_index`)
   * cannot carry a key per taxonomy field without exceeding MySQL's
   * 64-index-per-table limit, so on the database backend referenced taxonomy
   * terms are aggregated into the index-wide `all_taxonomy_term_uuids` field by
   * \Drupal\mukurtu_search\Plugin\search_api\processor\TaxonomyTermAggregates
   * instead.
   *
   * @param \Drupal\mukurtu_search\Event\FieldAvailableForIndexing $event
   *   Response event.
   */
  public function indexTaxonomyField(FieldAvailableForIndexing $event) {
    $indexes = ['mukurtu_default_solr_index'];
    if ($event->entity_type_id == 'node' && $event->field_definition->getType() == 'entity_reference' && $event->field_definition->getSetting('target_type') == 'taxonomy_term') {
      $field_name = $event->field_definition->getName();
      $field_id = "{$event->entity_type_id}__{$field_name}__uuid";
      $property_path = "{$field_name}:entity:uuid";
      $label = "{$event->field_definition->getLabel()} » Taxonomy term » UUID";
      $event->indexField($indexes, $field_id, $property_path, $label);
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
