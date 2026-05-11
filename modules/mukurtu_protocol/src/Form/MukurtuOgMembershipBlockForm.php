<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgMembershipInterface;

/**
 * Confirm form for blocking a single OG membership.
 */
class MukurtuOgMembershipBlockForm extends ConfirmFormBase {

  protected OgMembership $membership;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_og_membership_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Block %user from %group?', [
      '%user' => $this->membership->getOwner()->getDisplayName(),
      '%group' => $this->membership->getGroup()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->membership->getGroupEntityType() === 'community') {
      return $this->t('The user will not be able to access content from this community or its closed protocols. You can unblock them at any time from the members list.');
    }
    return $this->t('The user will not be able to access content from this protocol. You can unblock them at any time from the members list.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Block');
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

    if ($this->membership->getState() === OgMembershipInterface::STATE_BLOCKED) {
      $this->messenger()->addWarning($this->t('%user is already blocked from %group.', [
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
    $this->membership->setState(OgMembershipInterface::STATE_BLOCKED)->save();

    $this->messenger()->addStatus($this->t('%user has been blocked from %group.', [
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
