<?php

namespace Drupal\mukurtu_content_warnings\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

// class ContentWarningsField extends FieldItemList implements FieldItemListInterface {

//   use ComputedItemListTrait;

//   protected function computeValue() {
//     $entity = $this->getEntity();

//     // Content warnings only work with protocol controlled media entities.
//     if ($entity->getEntityTypeId() != 'media' || !($entity instanceof CulturalProtocolControlledInterface)) {
//       return;
//     }

//     // Check warnings. First get the content's communities.
//     $communities = $entity->getCommunities();
//     if (empty($communities)) {
//       return;
//     }

//     // Check each community's warning config.
//     foreach ($communities as $community) {
//       if (!$community->hasField('field_content_warning_triggers')) {
//         continue;
//       }

//       $warnings = $community->get('field_content_warning_triggers')->referencedEntities();
//       foreach ($warnings as $delta => $warning) {
//         $trigger_terms = $warning->get('field_content_warning_terms')->referencedEntities();
//         if ($this->checkConditions($trigger_terms)) {
//           $this->list[$delta] = $this->createItem($delta, $warning->get('field_content_warning_text')->value);
//           continue;
//         }
//       }
//     }
//   }

//   protected function checkConditions($terms) {
//     // Check if any of terms exist in this entity.
//     $entity = $this->getEntity();
//     $entityFieldManager = \Drupal::service('entity_field.manager');
//     $fields = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

//     foreach ($fields as $field) {
//       if ($field->getType() == 'entity_reference') {
//         $settings = $field->getSettings();
//         if ($settings['target_type'] == 'taxonomy_term') {
//           $values = $entity->get($field->getName())->referencedEntities();
//           foreach ($values as $value) {
//             foreach ($terms as $term) {
//               if ($value->id() == $term->id()) {
//                 return TRUE;
//               }
//             }
//           }
//         }
//       }
//     }

//     return FALSE;
//   }

// }
