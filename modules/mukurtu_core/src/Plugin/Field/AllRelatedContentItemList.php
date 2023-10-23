<?php

namespace Drupal\mukurtu_core\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mukurtu_core\Event\RelatedContentComputationEvent;

/**
 * AllRelatedContentItemList class to implement 'All Related Content' field.
 */
class AllRelatedContentItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $config = \Drupal::config('mukurtu.settings');
    $defaultRelatedContentOption = $config->get('mukurtu_related_content_display') ?? 'computed';
    $entity = $this->getEntity();

    if (!$entity->hasField('field_related_content')) {
      return;
    }

    // Get the local refs for the related content field, if present.
    $relatedIds = [];
    $delta = 0;

    $related = $entity->get('field_related_content')->referencedEntities();
    foreach ($related as $relatedContent) {
      $this->list[$delta] = $this->createItem($delta++, $relatedContent->id());

      // Track IDs of content we've already added so we don't dupe them later.
      $relatedIds[] = $relatedContent->id();
    }

    // If site is configured to display the related content field as is,
    // we are done at this point.
    if ($defaultRelatedContentOption == 'localonly') {
      return;
    }

    // Query for other content referencing this entity and add them to the
    // related content display.
    $query = \Drupal::entityQuery('node');

    // Get other related content via event.
    $event = new RelatedContentComputationEvent($entity, $query);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event = $event_dispatcher->dispatch($event, RelatedContentComputationEvent::EVENT_NAME);
    $query = $event->query;

    $event->relatedContentConditionGroup->condition('field_related_content', $entity->id());
    $query->condition($event->relatedContentConditionGroup)
      ->condition('nid', $entity->id(), '!=')
      ->condition('status', TRUE);
    $results = $query->execute();

    foreach ($results as $relatedId) {
      // Don't add duplicates.
      if (!in_array($relatedId, $relatedIds)) {
        $this->list[$delta] = $this->createItem($delta++, $relatedId);
        $relatedIds[] = $relatedId;
      }
    }
  }

}
