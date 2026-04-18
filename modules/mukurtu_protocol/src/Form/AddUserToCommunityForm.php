<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * Form to enrol a specific user in one or more communities with optional roles.
 *
 * Linked from the post-approval workflow message on the Site Users page.
 */
class AddUserToCommunityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_add_user_to_community_form';
  }

  /**
   * Access callback.
   */
  public static function access(AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    $user = User::load($account->id());
    foreach (Og::getMemberships($user) as $membership) {
      if ($membership->getGroupBundle() === 'community' && $membership->hasPermission('manage members')) {
        return AccessResult::allowed()->cachePerUser();
      }
    }
    return AccessResult::forbidden()->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, User $user = NULL) {
    if (!$user) {
      return $form;
    }
    $form_state->set('target_uid', $user->id());

    $form['user_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Adding user'),
      '#markup' => $user->getDisplayName(),
    ];

    // Collect communities the current user can manage, excluding ones the
    // target user is already a member of.
    $current_user = User::load($this->currentUser()->id());
    $available_communities = [];

    if ($this->currentUser()->hasPermission('administer users')) {
      $all = \Drupal::entityTypeManager()->getStorage('community')->loadMultiple();
      foreach ($all as $community) {
        if (!$community->getMembership($user)) {
          $available_communities[$community->id()] = $community->getName();
        }
      }
    }
    else {
      foreach (Og::getMemberships($current_user) as $membership) {
        if ($membership->getGroupBundle() !== 'community') {
          continue;
        }
        if (!$membership->hasPermission('administer group') && !$membership->hasPermission('manage members')) {
          continue;
        }
        $community = $membership->getGroup();
        if ($community && !$community->getMembership($user)) {
          $available_communities[$community->id()] = $community->getName();
        }
      }
    }

    asort($available_communities);

    if (empty($available_communities)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('@user is already a member of all communities you manage.', ['@user' => $user->getDisplayName()]) . '</p>',
      ];
      return $form;
    }

    // Get the non-locked community roles (exclude member/non-member).
    $role_manager = \Drupal::service('og.role_manager');
    $all_roles = $role_manager->getRolesByBundle('community', 'community');
    $roles = array_filter($all_roles, fn($r) => !in_array($r->id(), [
      'community-community-member',
      'community-community-non-member',
    ]));

    // Build table header: Community name + one column per role.
    $header = [$this->t('Community')];
    foreach ($roles as $role) {
      $header[] = $role->getLabel();
    }

    $form['memberships'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No communities available.'),
    ];

    foreach ($available_communities as $cid => $name) {
      $form['memberships'][$cid]['community_name'] = [
        '#plain_text' => $name,
      ];
      foreach ($roles as $role) {
        $form['memberships'][$cid][$role->id()] = [
          '#type' => 'checkbox',
          '#title' => $role->getLabel(),
          '#title_display' => 'invisible',
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to communities'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('view.mukurtu_people.page_1'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('target_uid');
    $user = User::load($uid);

    $role_manager = \Drupal::service('og.role_manager');
    $all_roles = $role_manager->getRolesByBundle('community', 'community');
    $roles = array_filter($all_roles, fn($r) => !in_array($r->id(), [
      'community-community-member',
      'community-community-non-member',
    ]));

    $added = [];
    $memberships = $form_state->getValue('memberships') ?? [];

    foreach ($memberships as $cid => $values) {
      // Collect whichever roles were checked for this community.
      $selected_roles = [];
      foreach ($roles as $role) {
        if (!empty($values[$role->id()])) {
          $selected_roles[] = str_replace('community-community-', '', $role->id());
        }
      }

      // Only enrol if at least one role was selected.
      if (empty($selected_roles)) {
        continue;
      }

      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = \Drupal::entityTypeManager()->getStorage('community')->load($cid);
      if ($community) {
        $community->addMember($user, $selected_roles);
        $added[] = $community->getName();
      }
    }

    if (!empty($added)) {
      $this->messenger()->addStatus($this->t('@user has been added to: @communities.', [
        '@user' => $user->getDisplayName(),
        '@communities' => implode(', ', $added),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('No communities were selected. @user was not enrolled anywhere.', [
        '@user' => $user->getDisplayName(),
      ]));
    }

    $form_state->setRedirect('view.mukurtu_people.page_1');
  }

}
