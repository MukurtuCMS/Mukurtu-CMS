<?php

namespace Drupal\mukurtu_taxonomy\EventSubscriber;

use Drupal\mukurtu_core\Event\RelatedContentComputationEvent;
use Drupal\mukurtu_core\Event\RelatedContentProvenanceEvent;
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
      RelatedContentProvenanceEvent::EVENT_NAME => 'onRelatedContentProvenance',
    ];
  }

  /**
   * Add conditions to the related content computation to include taxonomy records.
   */
  public function onRelatedContentComputation(RelatedContentComputationEvent $event) {
    // Here we only care about nodes with a taxonomy record field.
    if ($event->node->hasField('field_other_names')) {
      $terms = $event->node->get('field_other_names')->referencedEntities();
    } elseif ($event->node->hasField('field_other_place_names')) {
      $terms = $event->node->get('field_other_place_names')->referencedEntities();
    } else {
      return $event;
    }

    // Is the node configured to be a taxonomy record?
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
    $fields = $this->getCandidateSearchFields();

    // Build a list of all the fields we should be searching.
    $searchFields = [];

    // Find all entity reference field references to this taxonomy term.
    foreach ($fields['taxonomy_term'] as $fieldname) {
      foreach ($terms as $term) {
        $searchFields[] = ['fieldname' => $fieldname, 'value' => $term->id(), 'operator' => NULL];
      }
    }

    // Find all entity reference field references to the taxonomy records.
    foreach ($fields['node'] as $fieldname) {
      $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->id(), 'operator' => NULL];
    }

    // Text Fields that support embeds. Search for embeds of the record.
    foreach ($fields['text'] as $fieldname) {
      $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->uuid(), 'operator' => 'CONTAINS'];
    }

    return $searchFields;
  }

  /**
   * Returns node fields relevant to related content matching, by type.
   *
   * Shared by referencedContentConditionForTerm() (builds entityQuery
   * conditions) and onRelatedContentProvenance() (inspects already-loaded
   * candidate nodes directly, since a query's OR conditions can't tell us
   * after the fact which branch matched).
   *
   * @return array
   *   An array with keys 'taxonomy_term', 'node', and 'text', each a list of
   *   node field names of that kind.
   */
  protected function getCandidateSearchFields(): array {
    $fields = $this->entityFieldManager->getActiveFieldStorageDefinitions('node');

    $searchFields = [
      'taxonomy_term' => [],
      'node' => [],
      'text' => [],
    ];

    foreach ($fields as $fieldname => $field) {
      if (!($field instanceof DataDefinitionInterface)) {
        continue;
      }

      // Skip computed fields, they have no table storage.
      if ($field->isComputed()) {
        continue;
      }

      if ($field->getType() == 'entity_reference') {
        if ($field->getSetting('target_type') == 'taxonomy_term') {
          $searchFields['taxonomy_term'][] = $fieldname;
        }
        if ($field->getSetting('target_type') == 'node') {
          $searchFields['node'][] = $fieldname;
        }
      }

      if (in_array($field->getType(), ['text', 'text_long', 'text_with_summary'])) {
        $searchFields['text'][] = $fieldname;
      }
    }

    return $searchFields;
  }

  /**
   * Determine why each auto-discovered candidate matched this record.
   *
   * For each candidate node, reports back which taxonomy vocabularies (if
   * any) it matched via, or 'other' if it only matched via a direct node
   * reference or an embedded UUID.
   */
  public function onRelatedContentProvenance(RelatedContentProvenanceEvent $event) {
    if ($event->record->hasField('field_other_names')) {
      $terms = $event->record->get('field_other_names')->referencedEntities();
    }
    elseif ($event->record->hasField('field_other_place_names')) {
      $terms = $event->record->get('field_other_place_names')->referencedEntities();
    }
    else {
      return;
    }

    if (empty($event->candidates) || empty($terms)) {
      return;
    }

    // Group the record's term IDs by vocabulary.
    $termIdsByVocabulary = [];
    foreach ($terms as $term) {
      $termIdsByVocabulary[$term->bundle()][] = $term->id();
    }

    $searchFields = $this->getCandidateSearchFields();

    foreach ($event->candidates as $nid => $candidate) {
      $vocabularies = [];
      $other = FALSE;

      foreach ($searchFields['taxonomy_term'] as $fieldname) {
        if (!$candidate->hasField($fieldname)) {
          continue;
        }
        $referencedTermIds = array_column($candidate->get($fieldname)->getValue(), 'target_id');
        foreach ($termIdsByVocabulary as $vid => $termIds) {
          if (array_intersect($referencedTermIds, $termIds)) {
            $vocabularies[$vid] = $vid;
          }
        }
      }

      foreach ($searchFields['node'] as $fieldname) {
        if (!$candidate->hasField($fieldname)) {
          continue;
        }
        $referencedNodeIds = array_column($candidate->get($fieldname)->getValue(), 'target_id');
        if (in_array($event->record->id(), $referencedNodeIds)) {
          $other = TRUE;
        }
      }

      foreach ($searchFields['text'] as $fieldname) {
        if (!$candidate->hasField($fieldname)) {
          continue;
        }
        foreach ($candidate->get($fieldname)->getValue() as $item) {
          if (!empty($item['value']) && str_contains($item['value'], $event->record->uuid())) {
            $other = TRUE;
            break;
          }
        }
      }

      if ($vocabularies || $other) {
        $event->provenance[$nid] = [
          'vocabularies' => array_values($vocabularies),
          'other' => $other,
        ];
      }
    }
  }

}
