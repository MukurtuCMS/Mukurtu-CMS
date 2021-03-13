<?php

namespace Drupal\mukurtu_content_warnings\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

class ContentWarningsField extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  protected function computeValue() {
    $entity = $this->getEntity();

    // Content warnings are only targeting media.
    if ($entity->getEntityTypeId() != 'media') {
      return;
    }

    // Check warnings. First get the content's communities.
    $communities_value = $entity->get('field_mukurtu_community')->getValue();
    $community_nids = [];
    foreach ($communities_value as $community_value) {
      $community_nids[] = $community_value['target_id'];
    }

    if (empty($community_nids)) {
      return;
    }

    $communities = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($community_nids);

    // Check each community's warning config.
    foreach ($communities as $community) {
      if (!$community->hasField('field_content_warning_triggers')) {
        continue;
      }

      $warnings = $community->get('field_content_warning_triggers')->referencedEntities();
      foreach ($warnings as $delta => $warning) {
        $trigger_terms = $warning->get('field_content_warning_terms')->referencedEntities();
        if ($this->checkConditions($trigger_terms)) {
          $this->list[$delta] = $this->createItem($delta, $warning->get('field_content_warning_text')->value);
          continue;
        }
      }
    }
  }

  protected function checkConditions($terms) {
    // Check if any of terms exist in this entity.
    $entity = $this->getEntity();
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    foreach ($fields as $field) {
      if ($field->getType() == 'entity_reference') {
        $settings = $field->getSettings();
        if ($settings['target_type'] == 'taxonomy_term') {
          $values = $entity->get($field->getName())->referencedEntities();
          foreach ($values as $value) {
            foreach ($terms as $term) {
              if ($value->id() == $term->id()) {
                return TRUE;
              }
            }
          }
        }
      }
    }

    return FALSE;
  }

}
