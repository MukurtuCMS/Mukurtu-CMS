<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\Core\Action\ActionBase;
use Drupal\og\Plugin\Action\DeleteOgMembership;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * This is a nonworking attempt to implement custom Mukurtu handling to delete
 * an OG group membership, within the context of OG membership overview's
 * VBO options. These are found on the Members page of communities and protocols
 * at routes mukurtu_protocol.community_members_list and
 * mukurtu_protocol.protocol_members_list, respectively. This code does not
 * handle the deletion of og memberships one by one. That is instead handled by
 * MukurtuOgMembershipDeleteForm.
 *
 * If the membership to be deleted is a community membership, it can only be
 * deleted if the user holding that membership DOESN'T HAVE protocol memberships
 * within the community.
 *
 * @see mukurtu_protocol.routing.yml
 * @see Drupal\mukurtu_protocol\Form\MukurtuOgMembershipDeleteForm
 *
 * @Action(
 *   id = "mukurtu_og_membership_delete_action",
 *   label = @Translation("Delete the selected membership(s)"),
 *   type = "og_membership"
 * )
 */
class MukurtuDeleteOgMembershipAction extends DeleteOgMembership
{
  /**
   * {@inheritdoc}
   */
  public function execute(?OgMembership $membership = NULL)
  {
    // This doesn't work because this custom execute() method is not firing.
    // The parent class's DeleteOgMembership's execute() fires instead, and I
    // don't know why because I have specifically overridden execute() myself.
    if (!$membership) {
      return;
    }

    // Check if the membership to delete is a community membership, and if so,
    // check if the user who owns that membership has any protocol memberships
    // within that community.

    // If they do, DO NOT DELETE the community membership. Show an error
    // message that the membership couldn't be deleted because the holder of
    // that membership still has protocol memberships within that community.
    if ($membership->getGroupEntityType() == 'community') {
      $entity = $membership->getEntity();
      $user = $entity->getOwner();
      $community = $entity->getGroup();
      /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
      $protocols = $community->getProtocols();
      foreach ($protocols as $protocol) {
        if ($protocol->getMembership($user)) {
          $message = $this->t('%user cannot be unsubscribed from %group: user still has membership(s) within %group\'s cultural protocol(s).', [
            '%user' => $entity->getOwner()->getDisplayName(),
            '%group' => $entity->getGroup()->label(),
          ]);
          $this->messenger()->addError($message);
          return;
        }
      }
    }
    $membership->delete();
  }
}
