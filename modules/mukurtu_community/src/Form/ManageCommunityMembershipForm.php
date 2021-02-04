<?php

namespace Drupal\mukurtu_community\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\og\OgRoleInterface;
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
    $form['community-memberships'] = [
      '#type' => 'table',
      '#caption' => $this->t('Community Memberships') . ' - ' . $community->getTitle(),
      '#header' => $headers,
    ];

    // Get current memberships.
    $membership_manager = \Drupal::service('og.membership_manager');
    $membershipIds = $membership_manager->getGroupMembershipIdsByRoleNames($community, [OgRoleInterface::AUTHENTICATED]);
    $memberships = \Drupal::entityTypeManager()->getStorage('og_membership')->loadMultiple($membershipIds);

    foreach ($memberships as $membership) {
      $user = $membership->getOwner();
      $delta = $user->id();
      // Start the row with all the checkboxes unchecked.
      $form['community-memberships'][$delta] = $row_default;

      // Get the user name.
      $form['community-memberships'][$delta]['uid'] = [
        '#type' => 'item',
        '#value' => $user->id(),
        '#title' => $user->get('name')->value,
      ];

      // Status.
      $form['community-memberships'][$delta]['state'] = [
        '#type' => 'item',
        '#value' => $membership->get('state')->value,
        '#title' => $membership->get('state')->value,
      ];

      // Check the roles the user has.
      $userRoles = $membership->getRoles();
      foreach ($userRoles as $roleId => $userRole) {
        $form['community-memberships'][$delta][$userRole->id()]['#default_value'] = TRUE;
      }
    }

    // Add user button.
    $destination = Url::fromRoute('<current>')->toString();
    $form['actions']['add_user'] = [
      '#type' => 'link',
      '#title' => $this->t('Add User'),
      '#url' => Url::fromRoute('entity.og_membership.add_form', ['entity_type_id' => $community->getEntityTypeId(), 'group' => $community->id(), 'og_membership_type' => 'default'], ['query' => ['destination' => $destination]]),
    ];

/*     $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ]; */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
