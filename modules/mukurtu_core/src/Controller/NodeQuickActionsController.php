<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolver;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolverInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles quick publish/unpublish/transition actions on individual nodes.
 */
class NodeQuickActionsController extends ControllerBase {

  public function __construct(
    protected readonly ModerationInformationInterface $moderationInfo,
    protected readonly ModerationTransitionAccessResolverInterface $transitionAccess,
    protected readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('content_moderation.moderation_information'),
      // Not a services.yml entry -- see NodeRowActionsField::create() for
      // why this stays a lazy, plugin-local instantiation.
      new ModerationTransitionAccessResolver($container->get('content_moderation.state_transition_validation')),
      $container->get('datetime.time'),
    );
  }

  public function publish(NodeInterface $node, Request $request): RedirectResponse {
    if ($this->moderationInfo->isModeratedEntity($node)) {
      $this->messenger()->addError($this->t("%title is under content moderation and can't be published or unpublished directly. Use the state actions in the Actions menu instead.", ['%title' => $node->label()]));
      return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
    }
    $node->setPublished()->save();
    $this->messenger()->addStatus($this->t('%title has been published.', ['%title' => $node->label()]));
    return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

  public function unpublish(NodeInterface $node, Request $request): RedirectResponse {
    if ($this->moderationInfo->isModeratedEntity($node)) {
      $this->messenger()->addError($this->t("%title is under content moderation and can't be published or unpublished directly. Use the state actions in the Actions menu instead.", ['%title' => $node->label()]));
      return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
    }
    $node->setUnpublished()->save();
    $this->messenger()->addStatus($this->t('%title has been unpublished.', ['%title' => $node->label()]));
    return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

  public function transition(NodeInterface $node, string $to_state, Request $request): RedirectResponse {
    if (!$this->moderationInfo->isModeratedEntity($node)) {
      $this->messenger()->addError($this->t('%title is not under content moderation.', ['%title' => $node->label()]));
      return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
    }

    // Defense in depth: never trust the route parameter alone -- re-validate
    // against the node's actual current state and the viewer's actual
    // permissions (including the correct per-transition base operation --
    // archive/restore only need 'view', every other transition needs
    // 'update' -- see ModerationTransitionAccessResolver), in case the URL
    // is stale, bookmarked, or tampered with.
    $account = $this->currentUser();
    $transition = $this->transitionAccess->findAccessibleTransitionToState($node, $account, $to_state);
    if (!$transition) {
      $this->messenger()->addError($this->t('That state change is no longer available for %title.', ['%title' => $node->label()]));
      return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
    }

    $node->setNewRevision(TRUE);
    $node->set('moderation_state', $to_state);
    if ($node instanceof RevisionLogInterface) {
      $node->setRevisionCreationTime($this->time->getRequestTime());
      $node->setRevisionLogMessage($this->t('Moderation state changed to @state.', ['@state' => $to_state]));
      $node->setRevisionUserId($account->id());
    }
    $node->save();

    $this->messenger()->addStatus($this->t('%title has been moved to %state.', [
      '%title' => $node->label(),
      '%state' => $transition->to()->label(),
    ]));
    return $this->redirect('view.mukurtu_manage_all_content.mukurtu_manage_content');
  }

}
