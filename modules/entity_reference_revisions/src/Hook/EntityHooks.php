<?php

namespace Drupal\entity_reference_revisions\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

/**
 * Entity hooks service for the entity_reference_revisions module.
 */
final class EntityHooks {

  /**
   * Constructor of the entity hooks service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Implements hook_entity_delete() and hook_entity_revision_delete().
   *
   * Performs garbage collection for composite entities that were not removed
   * by EntityReferenceRevisionsItem.
   */
  #[Hook('entity_delete')]
  public function delete(EntityInterface $entity): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      $field_class = $this->fieldTypeManager->getPluginClass($field_definition->getType());
      if ($field_class == EntityReferenceRevisionsItem::class || is_subclass_of($field_class, EntityReferenceRevisionsItem::class)) {
        $target_entity_type_id = $field_definition->getSetting('target_type');
        $target_entity_storage = $this->entityTypeManager->getStorage($target_entity_type_id);
        $target_entity_type = $target_entity_storage->getEntityType();

        $parent_type_field = $target_entity_type->get('entity_revision_parent_type_field');
        $parent_id_field = $target_entity_type->get('entity_revision_parent_id_field');
        $parent_name_field = $target_entity_type->get('entity_revision_parent_field_name_field');

        if ($parent_type_field && $parent_id_field && $parent_name_field) {
          $entity_ids = $target_entity_storage
            ->getQuery()
            ->allRevisions()
            ->condition($parent_type_field, $entity->getEntityTypeId())
            ->condition($parent_id_field, $entity->id())
            ->condition($parent_name_field, $field_name)
            ->accessCheck(FALSE)
            ->execute();

          if (empty($entity_ids)) {
            continue;
          }
          $entity_ids = array_unique($entity_ids);
          foreach ($entity_ids as $entity_id) {
            $this->queueFactory->get('entity_reference_revisions_orphan_purger')->createItem([
              'entity_id' => $entity_id,
              'entity_type_id' => $target_entity_type_id,
            ]);
          }
        }
      }
    }
  }

}
