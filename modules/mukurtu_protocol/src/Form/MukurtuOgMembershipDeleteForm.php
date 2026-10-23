<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\og\Form\OgMembershipDeleteForm;

/**
 * Confirms the removal of a single member from a community or protocol.
 *
 * Reached from the "Remove @name from ..." operation on a members list row.
 * OG's form asks whether to "unsubscribe" the member, warns that the action
 * cannot be undone, and confirms with "Delete", none of which is accurate
 * here: the member can be added back. This uses the same wording as the bulk
 * removal form instead (#2009).
 *
 * @see \Drupal\mukurtu_protocol\Form\MukurtuOgMembershipRemoveMultipleForm
 */
class MukurtuOgMembershipDeleteForm extends OgMembershipDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Remove %user from %group?', [
      '%user' => $this->memberName(),
      '%group' => $this->groupLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = $this->getEntity();
    $args = [
      '%user' => $this->memberName(),
      '%group' => $this->groupLabel(),
    ];

    // The group is named here as well as in the question, because Gin
    // truncates the page title with an ellipsis and no title attribute.
    return $membership->getGroupEntityType() === 'protocol'
      ? $this->t('%user will lose their roles in %group and will no longer see content shared with that protocol. Their account is not affected, and you can add them back from the members list, but their roles will need to be set again.', $args)
      : $this->t('%user will lose their roles in %group and will no longer see content shared with that community. Their account is not affected, and you can add them back from the members list, but their roles will need to be set again.', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('Removed %user from %group.', [
      '%user' => $this->memberName(),
      '%group' => $this->groupLabel(),
    ]);
  }

  /**
   * The group's label, in the language the page is being built in.
   *
   * @return string
   *   The group label, or an empty string if the group has gone away.
   */
  protected function groupLabel(): string {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = $this->getEntity();
    $group = $membership->getGroup();
    if (!$group) {
      return '';
    }
    return (string) $this->entityRepository->getTranslationFromContext($group)->label();
  }

  /**
   * The display name of the member being removed.
   *
   * @return string
   *   The member's display name, or an empty string if the user has gone away.
   */
  protected function memberName(): string {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = $this->getEntity();
    $owner = $membership->getOwner();
    return $owner ? (string) $owner->getDisplayName() : '';
  }

}
