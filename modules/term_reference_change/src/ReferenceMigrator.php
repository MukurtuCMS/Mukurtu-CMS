<?php

namespace Drupal\term_reference_change;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Migrates references from one taxonomy term to the other.
 */
class ReferenceMigrator {

  /**
   * The reference finder.
   *
   * @var \Drupal\term_reference_change\ReferenceFinderInterface
   */
  private $referenceFinder;

  /**
   * ReferenceMigrator constructor.
   *
   * @param \Drupal\term_reference_change\ReferenceFinderInterface $referenceFinder
   *   The reference finder service.
   */
  public function __construct(ReferenceFinderInterface $referenceFinder) {
    $this->referenceFinder = $referenceFinder;
  }

  /**
   * Updates the term reference on all entities from the source to the target.
   *
   * @param \Drupal\taxonomy\TermInterface $sourceTerm
   *   The term to migrate away from.
   * @param \Drupal\taxonomy\TermInterface $targetTerm
   *   The term to migrate to.
   * @param array[] $limit
   *   List of entity ids keyed by their entity type to limit the migration to.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function migrateReference(TermInterface $sourceTerm, TermInterface $targetTerm, array $limit = []) {
    $referenceFieldNames = $this->findTermReferenceFieldNames(array_keys($limit));
    $referencingEntities = $this->referenceFinder->findReferencesFor($sourceTerm);

    foreach ($referencingEntities as $entityType => $entities) {
      if ($this->entityTypeShouldBeSkipped($entityType, $limit)) {
        continue;
      }

      foreach ($entities as $entity) {
        if ($this->entityShouldBeSkipped($entity, $limit)) {
          continue;
        }

        $this->updateReferencingEntity($sourceTerm, $targetTerm, $referenceFieldNames, $entity);
      }
    }
  }

  /**
   * Finds all names of term reference fields.
   *
   * @param string[] $limitToEntityTypes
   *   The entity types to limit to. If left empty, all types are allowed.
   *
   * @return string[]
   *   Array of entity reference field names for fields that reference taxonomy
   *   terms.
   */
  private function findTermReferenceFieldNames(array $limitToEntityTypes = []) {
    $fieldNames = [];

    foreach ($this->referenceFinder->findTermReferenceFields() as $entityType => $bundle) {
      if (!empty($limitToEntityTypes) && !in_array($entityType, $limitToEntityTypes)) {
        continue;
      }

      foreach ($bundle as $fieldsInBundle) {
        $fieldNames = array_merge($fieldNames, $fieldsInBundle);
      }
    }

    return $fieldNames;
  }

  /**
   * Determines if an entity should be skipped or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param array[] $limit
   *   The entity keys keyed by their entity id used to determine if the given
   *   entity should be skipped.
   *
   * @return bool
   *   TRUE if the entity should be skipped, FALSE otherwise.
   */
  private function entityShouldBeSkipped(EntityInterface $entity, array $limit) {
    if (empty($limit)) {
      return FALSE;
    }

    $entityTypeId = $entity->getEntityTypeId();
    if ($this->entityTypeShouldBeSkipped($entityTypeId, $limit)) {
      return TRUE;
    }

    return !in_array($entity->id(), $limit[$entityTypeId]);
  }

  /**
   * Determines if an entire entity type should be skipped or not.
   *
   * @param string $entityType
   *   The entity to check.
   * @param array[] $limit
   *   The entity keys keyed by their entity id used to determine if the given
   *   entity should be skipped.
   *
   * @return bool
   *   TRUE if the entity should be skipped, FALSE otherwise.
   */
  private function entityTypeShouldBeSkipped($entityType, array $limit) {
    if (empty($limit)) {
      return FALSE;
    }

    if (isset($limit[$entityType])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Changes the reference of an entity from the source term to the target term.
   *
   * @param \Drupal\taxonomy\TermInterface $sourceTerm
   *   The term to migrate from.
   * @param \Drupal\taxonomy\TermInterface $targetTerm
   *   The term to migrate to.
   * @param string[] $referenceFieldNames
   *   Names of possible entityReference fields.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateReferencingEntity(TermInterface $sourceTerm, TermInterface $targetTerm, array $referenceFieldNames, FieldableEntityInterface $entity) {
    foreach ($referenceFieldNames as $fieldName) {
      $value = [];
      if (!$entity->hasField($fieldName)) {
        continue;
      }

      $values = $entity->{$fieldName}->getValue();
      if (empty($values)) {
        continue;
      }

      $referenceUpdated = FALSE;
      foreach ($values as &$value) {
        if ($value['target_id'] !== $sourceTerm->id()) {
          continue;
        }

        $referenceUpdated = TRUE;
        $value['target_id'] = $targetTerm->id();
      }

      if (!$referenceUpdated) {
        continue;
      }

      $entity->{$fieldName}->setValue($this->removeDuplicates($values));
      $entity->save();
    }
  }

  /**
   * Removes duplicate references from the values array.
   *
   * @param array $values
   *   The values array.
   *
   * @return array
   *   The values array without duplicates.
   */
  private function removeDuplicates(array $values) {
    $unique = [];

    foreach ($values as $id => $value) {
      if (isset($unique[$value['target_id']])) {
        unset($values[$id]);
        continue;
      }

      $unique[$value['target_id']] = $value['target_id'];
    }

    return array_values($values);
  }

}
