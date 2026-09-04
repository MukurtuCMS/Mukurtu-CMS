<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\Transition;

/**
 * Resolves which moderation transitions a user may perform on a node.
 *
 * getValidTransitions() alone only checks whether the account holds the
 * underlying "use WORKFLOW transition X" permission (site-wide or in any
 * protocol) -- it says nothing about whether the account has real reach
 * into this specific node. Combining that with the right base entity
 * operation per transition is what this service centralizes, so the three
 * callers (row actions, the bulk action, and the quick-action controller)
 * apply the same rule consistently.
 */
interface ModerationTransitionAccessResolverInterface {

  /**
   * The base entity operation required to attempt a given transition.
   *
   * Archive/restore are pure moderation decisions, not content edits, so
   * they only require that the account can already view the node. Every
   * other transition still requires full update access.
   */
  public function baseOperationForTransition(string $transition_id): string;

  /**
   * Transitions on $entity that $account may actually perform right now.
   *
   * @return \Drupal\workflows\Transition[]
   *   Keyed by transition id.
   */
  public function getAccessibleTransitions(ContentEntityInterface $entity, AccountInterface $account): array;

  /**
   * The accessible transition (if any) that leads to $to_state.
   */
  public function findAccessibleTransitionToState(ContentEntityInterface $entity, AccountInterface $account, string $to_state): ?Transition;

}
