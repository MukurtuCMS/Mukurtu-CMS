<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of import templates.
 */
class MukurtuImportStrategyListBuilder extends ConfigEntityListBuilder {

  /**
   * Constructs a new MukurtuImportStrategyListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['entity_type_id'] = $this->t('Type');
    $header['bundle'] = $this->t('Sub-type');
    $header['uid'] = $this->t('Author');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof MukurtuImportStrategyInterface);
    $entity_type_id = $entity->getTargetEntityTypeId();
    $bundle = $entity->getTargetBundle();

    $entity_type_label = $this->getEntityTypeLabel($entity_type_id);
    $bundle_label = (!$bundle || $bundle === '-1')
      ? $entity_type_label
      : $this->getBundleLabel($entity_type_id, $bundle);

    $row['label'] = $entity->label();
    $row['entity_type_id'] = $entity_type_label;
    $row['bundle'] = $bundle_label;
    $owner = $entity->getOwner();
    $row['uid'] = $owner ? ['data' => $owner->toLink()->toRenderable()] : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * Gets the human-friendly label for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string
   *   The entity type label, or the ID if the definition is not found.
   */
  protected function getEntityTypeLabel(string $entity_type_id): string {
    $definition = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    return $definition ? (string) $definition->getLabel() : $entity_type_id;
  }

  /**
   * Gets the human-friendly label for a bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The bundle label, or the machine name if not found.
   */
  protected function getBundleLabel(string $entity_type_id, string $bundle): string {
    $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
    return (string) ($bundle_info[$bundle]['label'] ?? $bundle);
  }

}
