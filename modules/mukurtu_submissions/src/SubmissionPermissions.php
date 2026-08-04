<?php

namespace Drupal\mukurtu_submissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates one "submit {entity_type_id} {bundle} content" permission per
 * enabled mukurtu_submission_settings entity, mirroring core's dynamic
 * "create {type} content" node permissions.
 */
class SubmissionPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Returns dynamic per-bundle submission permissions.
   */
  public function permissions(): array {
    $permissions = [];
    /** @var \Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface[] $settings */
    $settings = $this->entityTypeManager->getStorage('mukurtu_submission_settings')->loadMultiple();

    foreach ($settings as $setting) {
      $entity_type_id = $setting->getTargetEntityTypeId();
      $bundle = $setting->getTargetBundle();
      $info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      $label = $info[$bundle]['label'] ?? $bundle;

      $permissions["submit $entity_type_id $bundle content"] = [
        'title' => $this->t('Submit %type content via the submission form', ['%type' => $label]),
        'dependencies' => [
          $setting->getConfigDependencyKey() => [$setting->getConfigDependencyName()],
        ],
      ];
    }

    return $permissions;
  }

}
