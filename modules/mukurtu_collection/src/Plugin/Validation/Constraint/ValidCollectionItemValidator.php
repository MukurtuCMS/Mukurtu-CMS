<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ValidCollectionItem constraint.
 */
class ValidCollectionItemValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a ValidCollectionItemValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof ValidCollectionItem);
    // Get the owning entity and its ID.
    $entity = $value->getEntity();

    $refs = [];
    foreach ($value as $item) {
      $target_id = $item->target_id;
      // Don't bother with empty value.
      if (empty($target_id)) {
        continue;
      }

      // No circular references allowed.
      if ($target_id === $entity->id()) {
        $this->context->addViolation($constraint->invalidCollectionItemSelfReference);
      }

      // Check for duplicates.
      if (in_array($target_id, $refs)) {
        $entity = $this->entityTypeManager->getStorage('node')->load($target_id);
        $title = '';
        if ($entity instanceof NodeInterface) {
          $title = $entity->getTitle() ?? '';
        }
        $this->context->addViolation($constraint->invalidCollectionItemDuplicate, ['@item' => $title]);
      }
      $refs[] = $target_id;
    }
  }

}
