<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MultipageValidNode constraint.
 */
class MultipageValidNodeConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Array of enabled bundles.
   *
   * @var array
   */
  protected array $enabledBundles;

  /**
   * Constructs an MultipageValidNodeConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
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

      // Item is unique.
      if (!in_array($target_id, $unique_items)) {
        $unique_items[] = $target_id;
      }
      // Item is a duplicate.
      else {
        $this->context->buildViolation($constraint->isDuplicate)
          ->setParameter('%value', $this->getTitle($target_id))
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }

      // Check if the node is already in an MPI.
      if ($this->alreadyInMPI($target_id)) {
        $this->context->buildViolation($constraint->alreadyInMPI)
          ->setParameter('%value', $this->getTitle($target_id))
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
      // Check if the node is a community record.
      if ($this->isCommunityRecord($target_id)) {
        $this->context->buildViolation($constraint->isCommunityRecord)
          ->setParameter('%value', $this->getTitle($target_id))
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($target_id)
          ->addViolation();
      }
      // Check if the node is of type enabled for multipage items.
      if (!$this->isEnabledBundleType($target_id)) {
        $this->context->buildViolation($constraint->notEnabledBundleType)
          ->setParameter('%value', $this->getTitle($target_id))
          ->atPath((string) $delta . '.target_id')
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
   * See if the value's type is one of the enabled bundles for multipage items.
   *
   * @param int $candidate_id
   *   Candidate node ID.
   *
   * @return bool
   *   TRUE if the value's type is one of the enabled bundles for multipage
   *   items, FALSE otherwise.
   */
  private function isEnabledBundleType(int $candidate_id): bool {
    $enabled_bundles = $this->getEnabledBundles();

    $candidate_node = $this->entityTypeManager->getStorage('node')->load($candidate_id);
    if (!$candidate_node) {
      return FALSE;
    }
    return in_array($candidate_node->bundle(), $enabled_bundles);
  }

  /**
   * Fetch enabled bundles.
   *
   * @return array
   *   Array of enabled bundles.
   */
  protected function getEnabledBundles(): array {
    if (!isset($this->enabledBundles)) {
      $config = $this->configFactory->get('mukurtu_multipage_items.settings');
      $bundles_config = $config->get('bundles_config') ?? [];
      $this->enabledBundles = array_keys(array_filter($bundles_config));
    }
    return $this->enabledBundles;
  }

  /**
   * Fetch the node title.
   *
   * @param int $candidate_id
   *   Candidate node ID.
   * @return string
   *   Node title.
   */
  protected function getTitle(int $candidate_id): string {
    $entity = $this->entityTypeManager->getStorage('node')->load($candidate_id);
    if (!$entity) {
      return '';
    }
    return $entity->label();
  }

}
