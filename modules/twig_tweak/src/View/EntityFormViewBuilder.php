<?php

namespace Drupal\twig_tweak\View;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Entity form view builder.
 */
class EntityFormViewBuilder {

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs an EntityFormViewBuilder object.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * Gets the built and processed entity form for the given entity type.
   *
   * @todo Add langcode parameter.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $form_mode
   *   (optional) The mode identifying the form variation to be returned.
   * @param bool $check_access
   *   (optional) Indicates that access check is required.
   *
   * @return array
   *   The processed form for the given entity type and form mode.
   */
  public function build(EntityInterface $entity, string $form_mode = 'default', bool $check_access = TRUE): array {
    $build = [];

    $operation = $entity->isNew() ? 'create' : 'update';
    $access = $check_access ? $entity->access($operation, NULL, TRUE) : AccessResult::allowed();
    if ($access->isAllowed()) {
      $build = $this->entityFormBuilder->getForm($entity, $form_mode);
    }

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->addCacheableDependency($entity)
      ->applyTo($build);

    return $build;
  }

}
