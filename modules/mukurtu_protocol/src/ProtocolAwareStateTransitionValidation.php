<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\Transition;
use Drupal\workflows\WorkflowInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\content_moderation\ModerationInformationInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class ProtocolAwareStateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * @var \Drupal\mukurtu_protocol\MukurtuPermissionsCheckInterface
   */
  protected $mukurtuPermissionChecker;

  /**
   * Stores the possible state transitions.
   *
   * @var array
   */
  protected $possibleTransitions = [];

  /**
   * Constructs a new StateTransitionValidation.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(ModerationInformationInterface $moderation_info) {
    $this->moderationInfo = $moderation_info;
    $this->mukurtuPermissionChecker = \Drupal::service('access_check.user.mukurtu_permission');
  }

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);

    return array_filter($current_state->getTransitions(), function (Transition $transition) use ($workflow, $user) {
      $permissions = ['site:use ' . $workflow->id() . ' transition ' . $transition->id(), 'protocol:use ' . $workflow->id() . ' transition ' . $transition->id()];
      return $this->mukurtuPermissionChecker->hasPermissions($user, $permissions, 'OR');
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, AccountInterface $user, ContentEntityInterface $entity) {
    $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($original_state->id(), $new_state->id());
    $permissions = ['site:use ' . $workflow->id() . ' transition ' . $transition->id(), 'protocol:use ' . $workflow->id() . ' transition ' . $transition->id()];
    return $this->mukurtuPermissionChecker->hasPermissions($user, $permissions, 'OR');
  }

}
