<?php

namespace Drupal\mukurtu_community\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\og\Og;
use Drupal\og\OgRoleInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\Core\Url;

class ManageCommunityMembershipForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_manage_community_membership_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $buildInfo = $form_state->getBuildInfo();
    $community = $buildInfo['args'][0] ?? NULL;
    if (!$community || $community->bundle() != 'community') {
      return $form;
    }
    $element = 'community-memberships-' . $community->id();

    // Get available roles.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle($community->getEntityTypeId(), $community->bundle());
    $headers = [$this->t('User'), $this->t('Membership State')];
    $row_default = ['uid' => '', 'state' => ''];
    foreach ($roles as $roleId => $role) {
      $headers[] = $role->getLabel();
      $row_default[$roleId] = [
        '#type' => 'checkbox',
        '#title' => '',
        '#default_value' => FALSE,
      ];
    }

    // Initialize the table.
    $form[$element] = [
      '#type' => 'table',
      '#caption' => $this->t('Community Memberships') . ' - ' . $community->getTitle(),
      '#header' => $headers,
    ];

    // Add JS.
    $form[$element]['#attached']['library'][] = 'mukurtu_community/mukurtu-community-role-management';

    // Get current memberships.
    $membership_manager = \Drupal::service('og.membership_manager');
    $membershipIds = $membership_manager->getGroupMembershipIdsByRoleNames($community, [OgRoleInterface::AUTHENTICATED]);
    $memberships = \Drupal::entityTypeManager()->getStorage('og_membership')->loadMultiple($membershipIds);

    foreach ($memberships as $membership) {
      $user = $membership->getOwner();
      $delta = $user->id();
      // Start the row with all the checkboxes unchecked.
      $form[$element][$delta] = $row_default;

      // Get the user name.
      $form[$element][$delta]['uid'] = [
        '#type' => 'item',
        '#value' => $user->id(),
        '#title' => $user->get('name')->value,
      ];

      // Status.
      $form[$element][$delta]['state'] = [
        '#type' => 'item',
        '#value' => $membership->get('state')->value,
        '#title' => $membership->get('state')->value,
      ];

      // Check the roles the user has.
      $userRoles = $membership->getRoles();
      foreach ($userRoles as $roleId => $userRole) {
        $form[$element][$delta][$userRole->id()]['#default_value'] = TRUE;
      }
    }

    // Add user button.
    $destination = Url::fromRoute('<current>')->toString();
    $form[$element]['actions']['add_user'] = [
      '#type' => 'link',
      '#title' => $this->t('Add User'),
      '#url' => Url::fromRoute('entity.og_membership.add_form', ['entity_type_id' => $community->getEntityTypeId(), 'group' => $community->id(), 'og_membership_type' => 'default'], ['query' => ['destination' => $destination]]),
    ];

    $form[$element . '-submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $buildInfo = $form_state->getBuildInfo();
    $community = $buildInfo['args'][0] ?? NULL;
    if (!$community) {
      return;
    }

    $mukurtuMembershipManager = \Drupal::service('mukurtu_protocol.membership_manager');

    // Get all available roles for communities.
    $roleManager = \Drupal::service('og.role_manager');
    $allCommunityRoles = $roleManager->getRolesByBundle($community->getEntityTypeId(), $community->bundle());

    // Get the submitted values from the form.
    $element = 'community-memberships-' . $community->id();
    $values = $form_state->getValues();

    // TODO: This should all be batched.
    foreach ($values[$element] as $uid => $roles) {
      // Load the user account.
      $account = \Drupal\user\Entity\User::load($uid);
      if (!$account) {
        continue;
      }

      // First handle member/non-member.
      if ($roles['node-community-member'] === 0 || $roles['node-community-non-member'] !== 0) {
        $mukurtuMembershipManager->removeMember($community, $account);
      } else {
        $mukurtuMembershipManager->addMember($community, $account);

        // Handle roles.
        $membership = Og::getMembership($community, $account, OgMembershipInterface::ALL_STATES);

        // Build a list of enabled roles.
        $enabledRoles = [];
        foreach ($roles as $role => $status) {
          if (!in_array($role, ['uid', 'state', 'node-protocol-non-member'])) {
            if ($status !== 0 && isset($allCommunityRoles[$role])) {
              $enabledRoles[] = $allCommunityRoles[$role];
            }
          }
        }

        // Set the roles and save the user's membership.
        $membership->setRoles($enabledRoles);
        $membership->save();
      }
    }

    // Display a status message telling the user the community membership was updated.
    \Drupal::messenger()->addStatus($this->t('Updated memberships for community %community', ['%community' => $community->getTitle()]));
  }

}
