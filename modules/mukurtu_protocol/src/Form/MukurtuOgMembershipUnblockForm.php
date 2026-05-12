<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgMembershipInterface;

/**
 * Confirm form for unblocking (activating) a single blocked OG membership.
 */
class MukurtuOgMembershipUnblockForm extends ConfirmFormBase {

  protected OgMembership $membership;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_og_membership_unblock_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Unblock %user from %group?', [
      '%user' => $this->membership->getOwner()->getDisplayName(),
      '%group' => $this->membership->getGroup()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $group_type = $this->membership->getGroupEntityType();
    if ($group_type === 'community') {
      return $this->t('The user will be restored to active membership and will regain access to this community and its strict protocols.');
    }
    return $this->t('The user will be restored to active membership and will regain access to this protocol.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unblock');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getMembersListUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OgMembership $og_membership = NULL) {
    $this->membership = $og_membership;

    if ($this->membership->getState() !== OgMembershipInterface::STATE_BLOCKED) {
      $this->messenger()->addWarning($this->t('%user is not blocked from %group.', [
        '%user' => $this->membership->getOwner()->getDisplayName(),
        '%group' => $this->membership->getGroup()->label(),
      ]));
      return $this->redirect($this->getMembersListUrl()->getRouteName(), $this->getMembersListUrl()->getRouteParameters());
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->membership->setState(OgMembershipInterface::STATE_ACTIVE)->save();
    node_access_rebuild(TRUE);

    $this->messenger()->addStatus($this->t('%user has been unblocked from %group.', [
      '%user' => $this->membership->getOwner()->getDisplayName(),
      '%group' => $this->membership->getGroup()->label(),
    ]));

    $form_state->setRedirectUrl($this->getMembersListUrl());
  }

  protected function getMembersListUrl(): Url {
    $group_type = $this->membership->getGroupEntityType();
    $group_id = $this->membership->getGroupId();

    if ($group_type === 'community') {
      return Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $group_id]);
    }
    return Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $group_id]);
  }

}
