<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\BulkEdit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\views_bulk_edit\Plugin\Action\ModifyEntityValues;

/**
 * Views Bulk Edit's "Modify field values" action, safe across batches.
 *
 * A value that references a new entity, such as a keyword typed into an
 * autocreate widget, is stored in the action configuration as an unsaved
 * entity. VBO restores that configuration fresh for every batch, so each
 * batch after the first tried to save the same entity again and failed on
 * its UUID. This resolves such references to an ID before applying them:
 * the existing entity with that UUID if there is one, otherwise the entity
 * saved once.
 *
 * Lives outside Plugin/Action because plugin discovery reflects every class
 * there, and this one's parent only loads when views_bulk_edit is enabled.
 * mukurtu_core swaps it in with hook_action_info_alter().
 *
 * @see \Drupal\mukurtu_core\Hook\BulkEditHooks::actionInfoAlter()
 */
class MukurtuModifyEntityValues extends ModifyEntityValues {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->resolveNewEntities();
    return parent::execute($entity);
  }

  /**
   * Replaces unsaved referenced entities in the configured values with IDs.
   */
  protected function resolveNewEntities(): void {
    foreach ($this->configuration as $entity_type_id => $bundles) {
      if (!is_array($bundles)) {
        continue;
      }
      foreach ($bundles as $bundle => $settings) {
        foreach ($settings['values'] ?? [] as $field => $items) {
          foreach ($items as $delta => $item) {
            $referenced = $item['entity'] ?? NULL;
            if ($referenced instanceof EntityInterface && $referenced->isNew()) {
              $this->configuration[$entity_type_id][$bundle]['values'][$field][$delta] = ['target_id' => $this->resolveId($referenced)];
            }
          }
        }
      }
    }
  }

  /**
   * Returns the ID of the saved entity with this entity's UUID.
   */
  protected function resolveId(EntityInterface $entity): int|string {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $existing = $storage->loadByProperties(['uuid' => $entity->uuid()]);
    if ($existing) {
      return reset($existing)->id();
    }
    $entity->save();
    return $entity->id();
  }

}
