<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views\ViewExecutable;

/**
 * VBO for removing users from a community.
 *
 * @Action(
 *   id = "mukurtu_remove_community_membership_action",
 *   label = @Translation("Remove user(s) from a community"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuRemoveCommunityMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity || !($entity instanceof User)) {
      return;
    }
    /** @var \Drupal\user\Entity\User $entity */

    // Load the community we are altering memberships for.
    $community_id = $this->configuration['community'];
    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    $community = \Drupal::entityTypeManager()->getStorage('community')->load($community_id);

    // Prevent removing a user who still belongs to a child protocol.
    foreach ($community->getProtocols() as $protocol) {
      if ($protocol->getMembership($entity)) {
        \Drupal::messenger()->addWarning($this->t('Could not remove %user from the community because they still have protocol roles. Remove them from all protocols first.', ['%user' => $entity->getDisplayName()]));
        return;
      }
    }

    $community->removeMember($entity);

    return $this->t('Removed user @username from community @community', ['@username' => $entity->getDisplayName(), '@community' => $community->getName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof User) {
      // Load the community we are altering memberships for.
      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = \Drupal::entityTypeManager()->getStorage('community')->load($this->configuration['community']);

      // Don't let a user remove themself. Probably a mistake for a batch operation.
      if ($account->id() === $object->id()) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if target user is already a member.
      if (!$community->getMembership($object)) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if requesting user can modify this community's membership.
      // The protocol-role guard (a user cannot be removed while enrolled in a
      // child protocol) is enforced in execute(), not here, so that the row
      // checkbox remains selectable and the admin receives an explicit warning
      // rather than a silently disabled action.
      $membership = $community->getMembership($account);
      if ($membership && ($membership->hasPermission('administer group') || $membership->hasPermission('manage members'))) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
      }
    }

    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool {
    // Get the OG Memberships for account.
    $memberships = array_filter(Og::getMemberships($account), fn ($m) => $m->getGroupBundle() === 'community');

    // User needs permission to manage membership for at least one community
    // to use this VBO.
    foreach ($memberships as $membership) {
      // Found one community where they have sufficent access, no need to check
      // the rest.
      if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $currentUser = User::load(\Drupal::currentUser()->id());

    // Get the communities the user has access to modify memberships for.
    $communities = [];
    $communityMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'community');
    $managerMemberships = array_filter($communityMemberships, fn ($m) => $m->hasPermission('administer group') || $m->hasPermission('manage members'));
    $managerCommunities = array_filter(array_map(fn ($m) => $m->getGroup(), $managerMemberships));

    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    foreach ($managerCommunities as $community) {
      $communities[$community->id()] = $community->getName();
    }

    $form['community'] = [
      '#title' => t('Community'),
      '#description' => t('Select which community you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $communities,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['community'] = $form_state->getValue('community');
  }

}
