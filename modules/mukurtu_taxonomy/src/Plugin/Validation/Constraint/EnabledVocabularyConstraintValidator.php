<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EnabledVocabulary constraint.
 */
class EnabledVocabularyConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs an EnabledVocabularyConstraintValidator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof EnabledVocabularyConstraint);

    if (!isset($value)) {
      return;
    }

    $config = $this->configFactory->get('mukurtu_taxonomy.settings');
    $enabled = $config->get($constraint->configKey) ?? [];

    // Build set of pre-existing reference IDs so we don't flag terms that
    // were saved before the vocabulary was disabled.
    $previously_referenced_ids = [];
    $entity = !empty($value->getParent()) ? $value->getEntity() : NULL;
    if ($entity && !$entity->isNew()) {
      $existing_entity = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->loadUnchanged($entity->id());
      if ($existing_entity) {
        $field_name = $value->getFieldDefinition()->getName();
        foreach ($existing_entity->{$field_name} as $item) {
          $previously_referenced_ids[$item->target_id] = $item->target_id;
        }
      }
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    foreach ($value as $item) {
      $target_id = $item->target_id;
      if (empty($target_id)) {
        continue;
      }

      // Skip terms that were already saved — they are grandfathered in.
      if (isset($previously_referenced_ids[$target_id])) {
        continue;
      }

      $term = $term_storage->load($target_id);
      if (!$term) {
        continue;
      }

      $bundle = $term->bundle();
      if (!in_array($bundle, $enabled)) {
        $this->context->addViolation($constraint->message, [
          '@term' => $term->label(),
          '@vocabulary' => $bundle,
        ]);
      }
    }
  }

}
