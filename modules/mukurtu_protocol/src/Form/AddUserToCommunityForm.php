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
 * Form to add a specific user to a community with optional roles.
 *
 * Accessible after approving a new user account, linked from the approval
 * workflow message on the Site Users page.
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

    // Build the list of communities the current user can manage.
    $current_user = User::load($this->currentUser()->id());
    $communities = [];

    if ($this->currentUser()->hasPermission('administer users')) {
      // Site admins see all communities.
      $all = \Drupal::entityTypeManager()->getStorage('community')->loadMultiple();
      foreach ($all as $community) {
        // Skip communities the target user is already a member of.
        if (!$community->getMembership($user)) {
          $communities[$community->id()] = $community->getName();
        }
      }
    }
    else {
      // Community managers see only the communities they manage.
      foreach (Og::getMemberships($current_user) as $membership) {
        if ($membership->getGroupBundle() !== 'community') {
          continue;
        }
        if (!$membership->hasPermission('administer group') && !$membership->hasPermission('manage members')) {
          continue;
        }
        $community = $membership->getGroup();
        if ($community && !$community->getMembership($user)) {
          $communities[$community->id()] = $community->getName();
        }
      }
    }

    asort($communities);

    if (empty($communities)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('@user is already a member of all communities you manage.', ['@user' => $user->getDisplayName()]) . '</p>',
      ];
      return $form;
    }

    $form['community'] = [
      '#type' => 'select',
      '#title' => $this->t('Community'),
      '#description' => $this->t('Select which community to add this user to.'),
      '#options' => $communities,
      '#required' => TRUE,
    ];

    // Role checkboxes — mirrors MukurtuAddCommunityMembershipAction.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('community', 'community');

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
    ];

    foreach ($roles as $role) {
      if (in_array($role->id(), ['community-community-member', 'community-community-non-member'])) {
        continue;
      }
      $form['roles'][$role->id()] = [
        '#type' => 'checkbox',
        '#title' => $role->getLabel(),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to community'),
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
    $community_id = $form_state->getValue('community');

    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    $community = \Drupal::entityTypeManager()->getStorage('community')->load($community_id);

    // Collect selected roles — same logic as the VBO action.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('community', 'community');
    unset($roles['community-community-member']);
    unset($roles['community-community-non-member']);

    $selected_roles = [];
    foreach ($roles as $role) {
      if ($form_state->getValue($role->id())) {
        $selected_roles[] = str_replace('community-community-', '', $role->id());
      }
    }

    $community->addMember($user, $selected_roles);

    $this->messenger()->addStatus($this->t('@user has been added to @community.', [
      '@user' => $user->getDisplayName(),
      '@community' => $community->getName(),
    ]));

    $form_state->setRedirect('view.mukurtu_people.page_1');
  }

}
