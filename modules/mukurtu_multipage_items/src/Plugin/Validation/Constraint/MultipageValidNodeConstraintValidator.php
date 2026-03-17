<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mukurtu_multipage_items\MultipageItemManager;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MultipageValidNode constraint.
 */
class MultipageValidNodeConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an MultipageValidNodeConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\mukurtu_multipage_items\MultipageItemManager $multipageItemManager
   *   The multipage item manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MultipageItemManager $multipageItemManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get(MultipageItemManager::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof EntityReferenceFieldItemListInterface) {
      return;
    }
    assert($constraint instanceof MultipageValidNodeConstraint);

    $unique_items = [];
    foreach ($value as $delta => $item) {
      $target_id = (int) $item->target_id;

      $target_entity = $item->entity;
      if (!$target_entity instanceof EntityInterface) {
        continue;
      }

      // Item is unique.
      if (!in_array($target_id, $unique_items)) {
        $unique_items[] = $target_id;
      }
      // Item is a duplicate.
      else {
        $this->context->buildViolation($constraint->isDuplicate)
          ->setParameter('%value', $target_entity->label())
          ->atPath($delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }

      // Check if the node is already in an MPI.
      if ($this->alreadyInMPI($target_id)) {
        $this->context->buildViolation($constraint->alreadyInMPI)
          ->setParameter('%value', $target_entity->label())
          ->atPath($delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
      // Check if the node is a community record.
      if ($this->isCommunityRecord($target_id)) {
        $this->context->buildViolation($constraint->isCommunityRecord)
          ->setParameter('%value', $target_entity->label())
          ->atPath($delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
      // Check if the node is of type enabled for multipage items.
      if (!$this->multipageItemManager->isEnabledBundleType($target_entity->bundle())) {
        $this->context->buildViolation($constraint->notEnabledBundleType)
          ->setParameter('%value', $target_entity->label())
          ->atPath($delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
      // Check that the user has access for the new nodes being added.
      if (!$this->hasAccessToAddedContent($target_entity)) {
        $this->context->buildViolation($constraint->noAccessToAddedContent)
          ->setParameter('%value', $target_entity->label())
          ->atPath($delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
    }
  }

  /**
   * See if the value represents a node already in an MPI.
   *
   * @param int $candidate_id
   *   Candidate node ID.
   *
   * @return bool
   *   TRUE if the value represents a node already in an MPI, FALSE otherwise.
   */
  protected function alreadyInMPI(int $candidate_id): bool {
    $query = $this->entityTypeManager->getStorage('multipage_item')->getQuery();
    $query->condition('field_pages', $candidate_id)
      ->accessCheck(FALSE);
    if ($id = $this->context->getRoot()->getEntity()->id()) {
      $query->condition('id', $id, '!=');
    }
    return (bool) $query->execute();
  }

  /**
   * See if the value is a community record.
   *
   * @param int $candidate_id
   *   Candidate node ID.
   *
   * @return bool
   *   TRUE if the value is a community record, FALSE otherwise.
   */
  protected function isCommunityRecord(int $candidate_id): bool {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $result = $nodeStorage->getQuery()
      ->condition('field_mukurtu_original_record', 0, '>')
      ->accessCheck(FALSE)
      ->execute();

    if (!$result) {
      return FALSE;
    }
    return in_array($candidate_id, $result);
  }

  /**
   * Check if the current user has access to add the target entity to an MPI.
   *
   * Users with 'administer multipage item' permission globally have access.
   * For protocol-controlled nodes, users must have 'administer multipage item'
   * permission in at least one of the node's owning protocols.
   *
   * @param \Drupal\Core\Entity\EntityInterface $target_entity
   *   The entity being added to the multipage item.
   *
   * @return bool
   *   TRUE if the current user has access to add the entity, FALSE otherwise.
   */
  protected function hasAccessToAddedContent(EntityInterface $target_entity): bool {
    // If this isn't a node, or doesn't have cultural protocol access control,
    // it shouldn't be allowed to be added.
    if (!$target_entity instanceof NodeInterface || !$target_entity instanceof CulturalProtocolControlledInterface) {
      return FALSE;
    }
    // Check for the multipage item admin permission in one of the owning
    // protocols.
    if ($protocols = $target_entity->getProtocolEntities()) {
      foreach ($protocols as $protocol) {
        $membership = $protocol->getMembership($this->currentUser);
        if ($membership && $membership->hasPermission('administer multipage item')) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
