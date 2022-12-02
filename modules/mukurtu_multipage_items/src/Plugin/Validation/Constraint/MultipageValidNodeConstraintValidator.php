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
    foreach ($value as $item) {
      // Check if the node is already in an MPI.
      if ($this->alreadyInMPI($item->getValue())) {
        $this->context->addViolation($constraint->alreadyInMPI, ['%value' => $item->getValue()['target_id']]);
      }
      // Check if the node is a community record.
      if ($this->isCommunityRecord($item->getValue())) {
        $this->context->addViolation($constraint->isCommunityRecord, ['%value' => $item->getValue()['target_id']]);
      }
      // Check if the node is of type enabled .
      if (!$this->isEnabledBundleType($item->getValue())) {
        $this->context->addViolation($constraint->notEnabledBundleType, ['%value' => $item->getValue()['target_id']]);
      }
    }
  }

  /**
   * See if the value represents a node already in an MPI.
   *
   * @param string $value
   */
  private function alreadyInMPI($value) {
    $table_mapping = \Drupal::entityTypeManager()->getStorage('multipage_item')->getTableMapping();
    $field_table = $table_mapping->getFieldTableName('field_pages');
    $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('multipage_item')['field_pages'];
    $field_column = $table_mapping->getFieldColumnName($field_storage_definitions, 'target_id');

    $connection = \Drupal::database();
    $result = $connection->select($field_table, 'f')
      ->fields('f', array($field_column))
      ->distinct(TRUE)
      ->execute()->fetchCol();

    // Could be refactored.
    if ($result) {
      if (in_array($value['target_id'], $result)) {
        return true;
      }
      return false;
    }
    // There are no MPI pages yet.
    return false;
  }

  /**
   * See if the value represents a node already in an MPI.
   *
   * @param string $value
   */
  private function isCommunityRecord($value) {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $nodeStorage->getQuery()
      ->condition('field_mukurtu_original_record', 0, '>')
      ->accessCheck(FALSE)
      ->execute();

    // Could be refactored.
    if ($result) {
      if (in_array($value['target_id'], $result)) {
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
    $bundles_config = \Drupal::config('mukurtu_multipage_items.settings')->get('bundles_config');
    $enabledBundles = array_keys(array_filter($bundles_config));

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $result = $nodeStorage->getQuery()
      ->condition('type', $enabledBundles, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    // Could be refactored.
    if ($result) {
      if (in_array($value['target_id'], $result)) {
        return true;
      }
    }
    return false;
  }
}
