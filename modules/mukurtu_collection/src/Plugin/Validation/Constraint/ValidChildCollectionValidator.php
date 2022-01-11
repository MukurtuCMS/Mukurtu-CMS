<?php

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidChildCollection constraint.
 */
class ValidChildCollectionValidator extends ConstraintValidator {
  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /**
     * @var \Drupal\mukurtu_collection\Entity\Collection $collection
     */
    $collection = $items->getEntity();

    /**
     * @var \Drupal\mukurtu_collection\Plugin\Validation\Constraint\ValidChildCollection $constraint
     */
    foreach ($items as $item) {
      $childCollectionId = $item->target_id ?? NULL;
      if (is_null($childCollectionId)) {
        continue;
      }

      if ($childCollectionId === $collection->id()) {
        $this->context->addViolation($constraint->invalidChildCollectionTargetSelf);
      }

      if ($this->inAnotherCollectionHierarchy($collection, $childCollectionId)) {
        $this->context->addViolation($constraint->invalidChildCollectionTargetUnavailable, ['%target_id' => $childCollectionId]);
      }
    }
  }

  /**
   * Check if a potential child collection is already in use.
   *
   * @param \Drupal\mukurtu_collection\Entity\Collection $collection
   *   The collection that wants to own the child collection.
   *
   * @param int $potentialChildCollectionId
   *   The ID of the child collection in question.
   *
   * @return bool
   *   True if in use by a collection other than $collection, false otherwise.
   */
  private function inAnotherCollectionHierarchy($collection, $potentialChildCollectionId): bool {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'collection')
      ->condition('field_child_collections', $potentialChildCollectionId, '=')
      ->accessCheck(FALSE);
    $results = $query->execute();

    // Not in use at all.
    if (count($results) == 0) {
      return FALSE;
    }

    // Already in use by the desired collection, which is fine.
    if ((count($results) == 1) && (reset($results) == $collection->id())) {
      return FALSE;
    }

    return TRUE;
  }

}
