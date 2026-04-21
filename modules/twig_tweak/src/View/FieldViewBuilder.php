<?php

namespace Drupal\twig_tweak\View;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;

/**
 * Field view builder.
 */
class FieldViewBuilder {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a FieldViewBuilder object.
   */
  public function __construct(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
  }

  /**
   * Returns the render array for a single entity field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param string|array $view_mode
   *   (optional) The view mode or display options.
   * @param string $langcode
   *   (optional) Language code to load translation.
   * @param bool $check_access
   *   (optional) Indicates that access check is required.
   *
   * @return array
   *   A render array for the field.
   *
   * @see \Drupal\Core\Entity\EntityViewBuilderInterface::viewField()
   */
  public function build(
    EntityInterface $entity,
    string $field_name,
    $view_mode = 'full',
    ?string $langcode = NULL,
    bool $check_access = TRUE,
  ): array {

    $build = [];

    $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
    $access = $check_access ? $entity->access('view', NULL, TRUE) : AccessResult::allowed();
    if ($access->isAllowed()) {
      if (!isset($entity->{$field_name})) {
        // @todo Trigger error here.
        return [];
      }
      $build = $entity->{$field_name}->view($view_mode);
    }

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->addCacheableDependency($entity)
      ->applyTo($build);

    return $build;
  }

}
