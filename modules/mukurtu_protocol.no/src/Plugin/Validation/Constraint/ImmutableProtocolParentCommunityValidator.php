<?php

namespace Drupal\mukurtu_protocol\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ImmutableProtocolParentCommunity constraint.
 */
class ImmutableProtocolParentCommunityValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $entity = $items->getEntity();
    $original = \Drupal::entityTypeManager()->getStorage('node')->load($entity->id());
    $delta = 0;

    if (!$entity->isNew()) {
      foreach ($items as $item) {
        $fieldName = $item->getFieldDefinition()->getName();
        $originalFieldValue = $original->get($fieldName);
        if ($item->target_id != $originalFieldValue[$delta++]->target_id) {
          $this->context->addViolation($constraint->parentCommunityChange);
        }
      }
    }
  }

}
