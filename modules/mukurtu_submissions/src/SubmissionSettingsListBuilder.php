<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Submission settings entities.
 */
class SubmissionSettingsListBuilder extends ConfigEntityListBuilder {

  /**
   * Constructs a new SubmissionSettingsListBuilder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
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
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['bundle'] = $this->t('Content type');
    $header['status'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof SubmissionSettingsInterface);
    $bundle_info = $this->entityBundleInfo->getBundleInfo($entity->getTargetEntityTypeId());
    $bundle_label = $bundle_info[$entity->getTargetBundle()]['label'] ?? $entity->getTargetBundle();

    $row['label'] = $entity->label();
    $row['bundle'] = $bundle_label;
    $row['status'] = $entity->status() ? $this->t('Yes') : $this->t('No');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    assert($entity instanceof SubmissionSettingsInterface);
    if ($entity->status()) {
      $operations['view_form'] = [
        'title' => $this->t('View form'),
        'weight' => 0,
        'url' => Url::fromRoute('mukurtu_submissions.submit', [
          'entity_type_id' => $entity->getTargetEntityTypeId(),
          'bundle' => $entity->getTargetBundle(),
        ]),
      ];
    }

    return $operations;
  }

}
