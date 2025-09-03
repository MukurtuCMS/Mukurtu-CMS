<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Form;

use Drupal\og\Form\OgMembershipDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom Mukurtu handling of the form for deleting a single OG membership.
 *
 * This custom form DOES work, unlike the attempt to customize deleting og
 * memberships via VBO at MukurtuDeleteOgMembershipAction.
 *
 * Prevents deletion of a community membership if the owner of the membership
 * has protocol memberships within that community.
 *
 * @see Drupal\mukurtu_protocol\Plugin\Action\MukurtuDeleteOgMembershipAction
 */
class MukurtuOgMembershipDeleteForm extends OgMembershipDeleteForm
{
  /**
   * {@inheritdoc}
   *
   * This is essentially a copy of ContentEntityDeleteForm's submitForm(), with
   * custom handling factored in.
   *
   * @see Drupal\Core\Entity\ContentEntityDeleteForm
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $message = $this->getDeletionMessage();

    // Make sure that deleting a translation does not delete the whole entity.
    if (!$entity->isDefaultTranslation()) {
      $untranslated_entity = $entity->getUntranslated();
      $untranslated_entity->removeTranslation($entity->language()->getId());
      $untranslated_entity->save();
      $form_state->setRedirectUrl($untranslated_entity->toUrl('canonical'));
    }
    else {
      // Check if we are deleting a community membership here.
      /** @var \Drupal\og\Entity\OgMembership $entity */
      if ($entity->getGroupEntityType() == 'community' && $this->hasProtocolMemberships()) {
        $message = $this->t('%user cannot be unsubscribed from %group: user still has membership(s) within %group\'s cultural protocol(s).', [
          '%user' => $entity->getOwner()->getDisplayName(),
          '%group' => $entity->getGroup()->label(),
        ]);
        $this->messenger()->addError($message);
      }
      else {
        $message = $this->getDeletionMessage();
        $entity->delete();
        $this->logDeletionMessage();
        $this->messenger()->addStatus($message);
      }
      $form_state->setRedirectUrl($this->getRedirectUrl());
    }
  }

  protected function hasProtocolMemberships()
  {
    /** @var \Drupal\og\Entity\OgMembership $entity */
    $entity = $this->getEntity();
    $user = $entity->getOwner();
    $community = $entity->getGroup();
    $hasProtocolMemberships = FALSE;
    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
    $protocols = $community->getProtocols();
    foreach ($protocols as $protocol) {
      if ($protocol->getMembership($user)) {
        $hasProtocolMemberships = TRUE;
        continue;
      }
    }
    return $hasProtocolMemberships;
  }
}

