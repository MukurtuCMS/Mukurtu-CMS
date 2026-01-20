<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_search\Event\FieldAvailableForIndexing;

/**
 * Mukurtu Taxonomy event subscriber.
 */
class TaxonomyFieldSearchIndexSubscriber implements EventSubscriberInterface {

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Field search indexing event handler.
   *
   * This is responsible for indexing the UUIDs of taxonomy fields. This is how
   * we display/browse referenced content on the canonical taxonomy term pages.
   *
   * @param \Drupal\mukurtu_search\Event\FieldAvailableForIndexing $event
   *   Response event.
   */
  public function indexTaxonomyField(FieldAvailableForIndexing $event): void {
    $indexes = ['mukurtu_browse_auto_index', 'mukurtu_default_solr_index'];
    if ($event->entity_type_id !== 'node') {
      return;
    }
    if ($event->field_definition->getType() !== 'entity_reference' || $event->field_definition->getSetting('target_type') !== 'taxonomy_term') {
      return;
    }

    $field_name = $event->field_definition->getName();

    // Check if this field is in the allow list.
    if (!in_array($field_name, $this->getAllowedTaxonomyFields())) {
      return;
    }

    $field_id = "{$event->entity_type_id}__{$field_name}__uuid";
    $property_path = "{$field_name}:entity:uuid";
    $label = "{$event->field_definition->getLabel()} » Taxonomy term » UUID";
    $event->indexField($indexes, $field_id, $property_path, $label);
  }

  /**
   * Get the list of allowed taxonomy fields for indexing.
   *
   * @return array
   *   An array of field names that should be indexed.
   */
  protected function getAllowedTaxonomyFields(): array {
    $allowed_fields = [];

    // Allow other modules to add fields to the allow list.
    $this->moduleHandler->alter('mukurtu_taxonomy_indexed_fields', $allowed_fields);

    return array_unique($allowed_fields);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FieldAvailableForIndexing::NEW_FIELD => ['indexTaxonomyField'],
      FieldAvailableForIndexing::UPDATED_FIELD => ['indexTaxonomyField'],
    ];
  }

}
