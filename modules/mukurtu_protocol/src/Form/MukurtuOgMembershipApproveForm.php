<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgMembershipInterface;

/**
 * Confirm form for approving a single pending OG membership.
 */
class MukurtuOgMembershipApproveForm extends ConfirmFormBase {

  protected OgMembership $membership;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_og_membership_approve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Approve membership request for %user in %group?', [
      '%user' => $this->membership->getOwner()->getDisplayName(),
      '%group' => $this->membership->getGroup()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The user will be granted active membership and will gain access to content shared with this group.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Approve');
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

    if ($this->membership->getState() !== OgMembershipInterface::STATE_PENDING) {
      $this->messenger()->addWarning($this->t('%user does not have a pending membership request for %group.', [
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

    $this->messenger()->addStatus($this->t('%user has been approved as a member of %group.', [
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
