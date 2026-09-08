<?php

namespace Drupal\mukurtu_core\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolver;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolverInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\workflows\Transition;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transitions a moderated node to a preconfigured moderation state.
 *
 * One plugin instance is configured per target state (draft, published,
 * archived) via the view's selected_actions preconfiguration, mirroring how
 * entity:publish_action:node / entity:unpublish_action:node are reused with
 * different label_overrides.
 *
 * @Action(
 *   id = "mukurtu_change_moderation_state_action",
 *   label = @Translation("Change moderation state"),
 *   type = "node",
 * )
 */
class ChangeModerationStateAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ModerationInformationInterface $moderationInfo,
    protected readonly StateTransitionValidationInterface $stateTransitionValidation,
    protected readonly ModerationTransitionAccessResolverInterface $transitionAccess,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly TimeInterface $time,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation'),
      // Not a services.yml entry -- see NodeRowActionsField::create() for
      // why this stays a lazy, plugin-local instantiation.
      new ModerationTransitionAccessResolver($container->get('content_moderation.state_transition_validation')),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('logger.factory')->get('mukurtu_core'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['target_state' => ''];
  }

  /**
   * Finds the transition (if any) among an entity's valid transitions that
   * leads to this action's configured target state.
   */
  protected function findTransitionToTargetState(ContentEntityInterface $entity, AccountInterface $account): ?Transition {
    $target_state = $this->configuration['target_state'] ?? '';
    if ($target_state === '') {
      return NULL;
    }
    foreach ($this->stateTransitionValidation->getValidTransitions($entity, $account) as $transition) {
      if ($transition->to()->id() === $target_state) {
        return $transition;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->checkAccess($object, $account);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Builds the access result for a single entity.
   */
  protected function checkAccess($object, ?AccountInterface $account) {
    if (!$object instanceof ContentEntityInterface || $account === NULL) {
      return AccessResult::forbidden("This action can't be applied to this item.");
    }
    if (($this->configuration['target_state'] ?? '') === '') {
      return AccessResult::forbidden('No target state configured for this action.');
    }
    if (!$this->moderationInfo->isModeratedEntity($object)) {
      return AccessResult::forbidden("This item isn't under content moderation.")->addCacheableDependency($object);
    }

    $transition = $this->findTransitionToTargetState($object, $account);
    if (!$transition) {
      return AccessResult::forbidden("That state change isn't available for this item.")->addCacheableDependency($object);
    }

    // Real per-node sovereignty gate (OG-scoped, walks the node's own
    // protocols). getValidTransitions() alone only checks whether the user
    // holds the permission in ANY protocol, not THIS node's protocol.
    // Archive/restore only require 'view' access (pure moderation
    // decisions, not content edits); every other transition still
    // requires 'update' -- see ModerationTransitionAccessResolver.
    $operation = $this->transitionAccess->baseOperationForTransition($transition->id());
    $access = $object->access($operation, $account, TRUE);
    if (!$access->isAllowed()) {
      return AccessResult::forbidden('You do not have permission to edit this content.')->inheritCacheability($access);
    }

    return AccessResult::allowed()->inheritCacheability($access)->addCacheableDependency($object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }
    $target_state = $this->configuration['target_state'] ?? '';

    // Defensive re-check: state may have changed between selection and
    // processing (VBO batches). Skip + log rather than force an invalid
    // transition.
    if (!$this->findTransitionToTargetState($entity, $this->currentUser)) {
      $this->logger->warning('Skipped moderation transition to @state for node @nid: transition no longer valid.', [
        '@state' => $target_state,
        '@nid' => $entity->id(),
      ]);
      return $this->t('Skipped — this state change is no longer available.');
    }

    $entity->setNewRevision(TRUE);
    $entity->set('moderation_state', $target_state);
    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionLogMessage($this->t('Moderation state changed to @state via bulk action.', ['@state' => $target_state]));
      $entity->setRevisionUserId($this->currentUser->id());
    }
    $entity->save();

    return $this->t('Moderation state changed.');
  }

}
