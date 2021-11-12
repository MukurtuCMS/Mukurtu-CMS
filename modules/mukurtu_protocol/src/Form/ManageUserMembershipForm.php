<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\og\Og;
//use Drupal\user\Entity\UserInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\og\OgRoleInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\Core\Url;
use Exception;

class ManageUserMembershipForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_manage_user_membership_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    if (!$user) {
      return $form;
    }

    // User.
    $form['user'] = ['#type' => 'hidden', '#value' => $user->id()];

    // Communities.
    $form['community'] = $this->buildTable($user, 'community');

    // Protocols.
    $form['protocol'] = $this->buildTable($user, 'protocol');

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  protected function buildTable(UserInterface $user, $bundle) {
    $node_types = node_type_get_names();
    if (!isset($node_types[$bundle])) {
      return [];
    }

    $bundle_label = $node_types[$bundle];

    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('node', $bundle);
    $memberId = "node-{$bundle}-member";
    $nonMemberId = "node-{$bundle}-non-member";
    $headers = [$bundle_label, $this->t('Membership State')];
    $row_default = ['nid' => '', 'state' => ''];
    foreach ($roles as $roleId => $role) {
      $headers[] = $role->getLabel();
      $row_default[$roleId] = [
        '#type' => 'checkbox',
        '#title' => '',
        '#default_value' => FALSE,
        '#return_value' => TRUE,
      ];
    }

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('@bundle_label Memberships', ['@bundle_label' => $bundle_label]),
      '#header' => $headers,
    ];

    // Get current memberships for the user we are managing.
    $memberships = Og::getMemberships($user);

    // We want to display the groups that the viewing user has
    // management permissions for but the user being edited
    // doesn't have membership for.
    $currentUserMemberships = Og::getMemberships($this->currentUser());
    $nonMemberGroups = [];
    foreach ($currentUserMemberships as $membership) {
      if ($membership->getGroupBundle() == $bundle) {
        if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
          $userMembership = Og::getMembership($membership->getGroup(), $user);
          if (!$userMembership) {
            $nonMemberGroups[$membership->getGroup()->id()] = $membership->getGroup();
          }
        }
      }
    }

    $memberships = $memberships;

    foreach ($memberships as $membership) {
      // Only dealing with the specific bundle.
      if ($membership->getGroupBundle() == $bundle) {

        // Get the group entity.
        $group = $membership->getGroup();
        if (!$group) {
          continue;
        }

        // Get the viewing user's membership for that group.
        $viewingMembership = Og::getMembership($group, $this->currentUser());
        if (!$viewingMembership) {
          continue;
        }

        // Don't display the row if the viewing user doesn't have
        // the correct rights.
        if ($viewingMembership->hasPermission('administer group') || $viewingMembership->hasPermission('manage members')) {
          $delta = $group->id();
          $table[$delta] = $row_default;

          $groupCommunity = NULL;
          if ($group->hasField(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)) {
            $groupCommunityId = $group->get(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)[0]->getValue();
            if (isset($groupCommunityId['target_id'])) {
              $groupCommunity = \Drupal::entityTypeManager()->getStorage('node')->load($groupCommunityId['target_id']);
            }
          }

          // Group Name.
          $title = $groupCommunity ? $groupCommunity->getTitle() . ': ' . $group->getTitle() : $group->getTitle();
          $table[$delta]['nid'] = [
            '#type' => 'item',
            '#value' => $delta,
            '#title' => $title,
          ];

          // Status.
          $table[$delta]['state'] = [
            '#type' => 'item',
            '#value' => $membership->get('state')->value,
            '#title' => $membership->get('state')->value,
          ];

          // Roles.
          $userRoles = $membership->getRoles();
          foreach ($userRoles as $roleId => $userRole) {
            $table[$delta][$userRole->id()]['#default_value'] = TRUE;
          }

          // State management.
          $name = "{$bundle}[{$delta}][{$memberId}]";
          foreach ($roles as $roleId => $role) {
            if ($roleId == $nonMemberId) {
              $table[$delta][$roleId]['#states'] = [
                'enabled' => [
                  ':input[name="' . $name . '"]' => ['checked' => FALSE],
                ],
              ];
            }

            if (!in_array($roleId, [$memberId, $nonMemberId])) {
              $table[$delta][$roleId]['#states'] = [
                'enabled' => [
                  ':input[name="' . $name . '"]' => ['checked' => TRUE],
                ],
              ];
            }
          }
        }
      }
    }

    // These are the groups current user can manage but
    // the subject user isn't a member of currently.
    foreach ($nonMemberGroups as $group) {
      $delta = $group->id();
      $row_default["node-{$group->bundle()}-non-member"]['#default_value'] = TRUE;
      $table[$delta] = $row_default;
      $groupCommunity = NULL;
      if ($group->hasField(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)) {
        $groupCommunity = $group->get(MUKURTU_PROTOCOL_FIELD_NAME_COMMUNITY)[0]->getEntity();
      }

      // Group Name.
      $title = $groupCommunity ? $groupCommunity->getTitle() . ': ' . $group->getTitle() : $group->getTitle();
      $table[$delta]['nid'] = [
        '#type' => 'item',
        '#value' => $delta,
        '#title' => $title,
      ];

      // Status.
      $table[$delta]['state'] = [
        '#type' => 'item',
        '#value' => $membership->get('state')->value,
        '#title' => $membership->get('state')->value,
      ];

      // State management.
      $name = "{$bundle}[{$delta}][{$memberId}]";
      foreach ($roles as $roleId => $role) {
        if ($roleId == $nonMemberId) {
          $table[$delta][$roleId]['#states'] = [
            'enabled' => [
              ':input[name="' . $name . '"]' => ['checked' => FALSE],
            ],
          ];
        }

        if (!in_array($roleId, [$memberId, $nonMemberId])) {
          $table[$delta][$roleId]['#states'] = [
            'enabled' => [
              ':input[name="' . $name . '"]' => ['checked' => TRUE],
            ],
          ];
        }
      }
    }

    return $table;
  }

  protected function buildRolesFromCheckboxes($group, $checkboxes) {
    $prefix = "{$group->getEntityTypeId()}-{$group->bundle()}";
    $roles = [];
    foreach ($checkboxes as $role_id => $value) {
      if (str_starts_with($role_id, $prefix)) {
        if ($value !== 0) {
          $roles[] = $role_id;
        }
      }
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mukurtuMembershipManager = \Drupal::service('mukurtu_protocol.membership_manager');
    $uid = $form_state->getValue("user");
    $entityTypeManager = \Drupal::entityTypeManager();
    $user = $entityTypeManager->getStorage('user')->load($uid);

    $communities = $form_state->getValue("community");
    $protocols = $form_state->getValue("protocol");

    foreach ($communities as $community) {
      $group = $entityTypeManager->getStorage('node')->load($community['nid']);
      $roles = $this->buildRolesFromCheckboxes($group, $community);
      $mukurtuMembershipManager->setRoles($group, $user, $roles);
    }

    foreach ($protocols as $protocol) {
      $group = $entityTypeManager->getStorage('node')->load($protocol['nid']);
      $roles = $this->buildRolesFromCheckboxes($group, $protocol);
      $mukurtuMembershipManager->setRoles($group, $user, $roles);
    }
  }

}
