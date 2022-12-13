<?php

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MultipageValidNode constraint.
 */
class MultipageValidNodeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $uniqueItems = [];

    foreach ($value as $item) {
      $id = $item->getValue()['target_id'];

      // Item is unique.
      if (!in_array($id, $uniqueItems)) {
        array_push($uniqueItems, $id);
      }
      // Item is a duplicate.
      elseif (in_array($id, $uniqueItems)) {
        $this->context->addViolation($constraint->isDuplicate, ['%value' => $this->getTitle($id)]);
      }
      // Check if the node is already in an MPI.
      if ($this->alreadyInMPI($id)) {
        $this->context->addViolation($constraint->alreadyInMPI, ['%value' => $this->getTitle($id)]);
      }
      // Check if the node is a community record.
      if ($this->isCommunityRecord($id)) {
        $this->context->addViolation($constraint->isCommunityRecord, ['%value' => $this->getTitle($id)]);
      }
      // Check if the node is of type enabled for multipage items.
      if (!$this->isEnabledBundleType($id)) {
        $this->context->addViolation($constraint->notEnabledBundleType, ['%value' => $this->getTitle($id)]);
      }
    }
  }

  /**
   * See if the value represents a node already in an MPI.
   *
   * @param string $value
   */
  private function alreadyInMPI($value) {
    // $value = [pages]
    $query = \Drupal::entityQuery('multipage_item')
      ->condition('field_pages', $value)
      ->condition('id', $this->context->getRoot()->getEntity()->id(), '!=')
      ->accessCheck(FALSE);
    $result = $query->execute();

    if ($result) {
      return true;
    }
    return false;
  }

  /**
   * See if the value is a community record.
   *
   * @param string $value
   */
  private function isCommunityRecord($value) {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $nodeStorage->getQuery()
      ->condition('field_mukurtu_original_record', 0, '>')
      ->accessCheck(FALSE)
      ->execute();

    if ($result) {
      if (in_array($value, $result)) {
        return true;
      }
    }
    return false;
  }

  /**
   * See if the value's type is one of the enabled bundles for multipages items.
   *
   * @param string $value
   */
  private function isEnabledBundleType($value)
  {
    $enabledBundles = $this->getEnabledBundles();

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $nodeStorage->getQuery()
      ->condition('type', $enabledBundles, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    if ($result) {
      if (in_array($value, $result)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Fetch enabled bundles.
   */
  private function getEnabledBundles() {
    if (!$this->enabledBundles) {
      $this->enabledBundles = array_keys(array_filter(\Drupal::config('mukurtu_multipage_items.settings')->get('bundles_config')));
    }
    return $this->enabledBundles;
  }

  /**
   * Fetch the node title.
   */
  private function getTitle($id) {
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($id);
    return $entity->label();
  }
}
