<?php

namespace Drupal\term_reference_change;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Finds entities referencing a term.
 */
class ReferenceFinder implements ReferenceFinderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $entityTypeBundleInfo;

  /**
   * ReferenceFinder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function findReferencesFor(TermInterface $term) {
    return $this->loadReferencingEntities($term);
  }

  /**
   * Loads all entities with a reference to the given term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to find references to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   All entities referencing this term.
   */
  private function loadReferencingEntities(TermInterface $term) {
    $referenceFields = $this->findTermReferenceFields();

    $referencingEntities = [];

    foreach ($referenceFields as $entityType => $bundles) {
      $entitiesOfType = $this->loadReferencingEntitiesOfType($term, $bundles, $entityType);

      if (empty($entitiesOfType)) {
        continue;
      }

      $referencingEntities[$entityType] = $entitiesOfType;
    }

    return $referencingEntities;
  }

  /**
   * Loads all entities of a given type with reference to the given term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to find references to.
   * @param array $bundles
   *   The entity bundles that might contain these references.
   * @param string $entityType
   *   The entity type to limit to.
   *
   * @return array
   *   The loaded entities.
   */
  private function loadReferencingEntitiesOfType(TermInterface $term, array $bundles, $entityType) {
    $referencingEntities = [];

    foreach ($bundles as $bundle) {
      foreach ($bundle as $fieldName) {
        $entities = $this->entityTypeManager->getStorage($entityType)
          ->loadByProperties([$fieldName => $term->id()]);
        $referencingEntities = array_merge($referencingEntities, $entities);
      }
    }

    return $referencingEntities;
  }

  /**
   * {@inheritdoc}
   */
  public function findTermReferenceFields() {
    $termReferenceFields = [];

    $entityTypes = $this->entityTypeManager->getDefinitions();
    foreach ($entityTypes as $entityType) {
      if (!$entityType->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }

      $referenceFields = $this->findTermReferenceFieldsForEntityType($entityType->id());
      if (empty($referenceFields)) {
        continue;
      }

      $termReferenceFields[$entityType->id()] = $referenceFields;
    }

    return $termReferenceFields;
  }

  /**
   * Finds all term reference fields for a given entity type.
   *
   * @param string $entityType
   *   The entity type name.
   *
   * @return array
   *   The term reference fields keyed by their respective bundle.
   */
  private function findTermReferenceFieldsForEntityType($entityType) {
    $bundleNames = array_keys($this->entityTypeBundleInfo->getBundleInfo($entityType));

    $referenceFields = [];
    foreach ($bundleNames as $bundleName) {
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundleName);
      foreach ($fieldDefinitions as $fieldDefinition) {
        if ($fieldDefinition->getType() !== 'entity_reference') {
          continue;
        }

        if ($fieldDefinition->getSetting('target_type') !== 'taxonomy_term') {
          continue;
        }

        if ($fieldDefinition->isComputed()) {
          continue;
        }

        // Exclude parent fields because they cause fatal errors during the
        // query. This is because they are currently a special case.
        // @see https://www.drupal.org/node/2543726
        if ($fieldDefinition->getName() === 'parent') {
          continue;
        }

        $referenceFields[$bundleName][] = $fieldDefinition->getName();
      }
    }

    return $referenceFields;
  }

}
