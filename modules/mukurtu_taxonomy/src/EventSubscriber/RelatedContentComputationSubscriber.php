<?php

namespace Drupal\mukurtu_taxonomy\EventSubscriber;

use Drupal\mukurtu_core\Event\RelatedContentComputationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Class RelatedContentComputationSubscriber.
 *
 * @package Drupal\mukurtu_taxonomy\EventSubscriber
 */
class RelatedContentComputationSubscriber implements EventSubscriberInterface, ContainerInjectionInterface {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RelatedContentComputationEvent::EVENT_NAME => 'onRelatedContentComputation',
    ];
  }

  /**
   * Add conditions to the related content computation to include taxonomy records.
   */
  public function onRelatedContentComputation(RelatedContentComputationEvent $event) {
    // Here we only care about nodes with the taxonomy record field.
    if (!$event->node->hasField('field_other_names')) {
      return $event;
    }

    // Is the node configured to be a taxonomy record?
    $terms = $event->node->get('field_other_names')->referencedEntities();
    $searchFields = $this->referencedContentConditionForTerm($terms, $event->node);

    // Return early if there are no supported fields to query.
    if (count($searchFields) == 0) {
      return $event;
    }

    // Query all those fields for references to the given media.
    $fieldConditions = count($searchFields) == 1 ? $event->query->andConditionGroup() : $event->query->orConditionGroup();

    // Add all the field conditions.
    foreach ($searchFields as $fieldCondition) {
      $fieldConditions->condition($fieldCondition['fieldname'], $fieldCondition['value'], $fieldCondition['operator']);
    }
    $event->relatedContentConditionGroup->condition($fieldConditions);

    return $event;
  }

  /**
   * Helper function. Take terms and a node being used as a taxonomy record and
   * return the field conditions we should use in the entityQuery.
   */
  protected function referencedContentConditionForTerm($terms, NodeInterface $record) {
    // Get all field definitions for nodes.
    //$field = $this->entityFieldManager->getFieldDefinitions('node');
    $fields = $this->entityFieldManager->getActiveFieldStorageDefinitions('node');

    // Build a list of all the fields we should be searching.
    $searchFields = [];

    // Entity Reference Fields.
    foreach ($fields as $fieldname => $field) {
      if (!($field instanceof DataDefinitionInterface)) {
        continue;
      }

      // Skip computed fields, they have no table storage.
      if ($field->isComputed()) {
        continue;
      }

      if ($field->getType() == 'entity_reference') {
        // Find all entity reference field references to this taxonomy term.
        if ($field->getSetting('target_type') == 'taxonomy_term') {
          foreach ($terms as $term) {
            $searchFields[] = ['fieldname' => $fieldname, 'value' => $term->id(), 'operator' => NULL];
          }
        }
        // Find all entity reference field references to the taxonomy records.
        if ($field->getSetting('target_type') == 'node') {
          $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->id(), 'operator' => NULL];
        }
      }

      // Text Fields that support embeds. Search for embeds of the record.
      if (in_array($field->getType(), ['text', 'text_long', 'text_with_summary'])) {
        $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->uuid(), 'operator' => 'CONTAINS'];
      }
    }

    return $searchFields;
  }

}
