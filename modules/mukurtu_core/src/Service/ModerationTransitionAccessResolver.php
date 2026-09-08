<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\Transition;

/**
 * {@inheritdoc}
 */
class ModerationTransitionAccessResolver implements ModerationTransitionAccessResolverInterface {

  /**
   * Transitions whose base gate is 'view' instead of 'update'.
   */
  protected const VIEW_GATED_TRANSITIONS = ['archive', 'restore'];

  public function __construct(
    protected readonly StateTransitionValidationInterface $stateTransitionValidation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function baseOperationForTransition(string $transition_id): string {
    return in_array($transition_id, self::VIEW_GATED_TRANSITIONS, TRUE) ? 'view' : 'update';
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessibleTransitions(ContentEntityInterface $entity, AccountInterface $account): array {
    $accessible = [];
    foreach ($this->stateTransitionValidation->getValidTransitions($entity, $account) as $transition) {
      $operation = $this->baseOperationForTransition($transition->id());
      if ($entity->access($operation, $account)) {
        $accessible[$transition->id()] = $transition;
      }
    }
    return $accessible;
  }

  /**
   * {@inheritdoc}
   */
  public function findAccessibleTransitionToState(ContentEntityInterface $entity, AccountInterface $account, string $to_state): ?Transition {
    foreach ($this->getAccessibleTransitions($entity, $account) as $transition) {
      if ($transition->to()->id() === $to_state) {
        return $transition;
      }
    }
    return NULL;
  }

}
