<?php

declare(strict_types=1);

namespace Drupal\actions_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create permissions for existing actions.
 */
final class ActionsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly ViewsBulkOperationsActionManager $actionManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.views_bulk_operations_action'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get permissions for Actions.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions(): array {
    $permissions = [];
    $entity_type_definitions = $this->entityTypeManager->getDefinitions();

    // Get definitions that will not be altered by actions_permissions.
    foreach ($this->actionManager->getDefinitions([
      'skip_actions_permissions' => TRUE,
      'nocache' => TRUE,
    ]) as $definition) {

      // Skip actions that define their own requirements.
      if (\array_key_exists('requirements', $definition) && \count($definition['requirements']) !== 0) {
        continue;
      }

      $id = 'execute ' . $definition['id'];
      $entity_type = NULL;
      if ($definition['type'] === '') {
        $entity_type = $this->t('all entity types');
        $id .= ' all';
      }
      elseif (\array_key_exists($definition['type'], $entity_type_definitions)) {
        $entity_type = $entity_type_definitions[$definition['type']]->getLabel();
        $id .= ' ' . $definition['type'];
      }

      if ($entity_type !== NULL) {
        $permissions[$id] = [
          'title' => $this->t('Execute the %action action on %type.', [
            '%action' => $definition['label'],
            '%type' => $entity_type,
          ]),
        ];
      }
    }

    return $permissions;
  }

}
