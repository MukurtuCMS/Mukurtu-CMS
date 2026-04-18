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
 * Two-step form to enrol a user in communities and their child protocols.
 *
 * Step 1 – community assignment table.
 * Step 2 – protocol assignment table (only shown when selected communities
 *           have child protocols; skipped automatically otherwise).
 */
class AddUserToCommunityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_add_user_to_community_form';
  }

  /**
   * Route access callback.
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

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the community OG roles (excluding member / non-member).
   */
  protected function getCommunityRoles(): array {
    $role_manager = \Drupal::service('og.role_manager');
    return array_filter(
      $role_manager->getRolesByBundle('community', 'community'),
      fn($r) => !in_array($r->id(), ['community-community-member', 'community-community-non-member'])
    );
  }

  /**
   * Returns the protocol OG roles (excluding member / non-member).
   */
  protected function getProtocolRoles(): array {
    $role_manager = \Drupal::service('og.role_manager');
    return array_filter(
      $role_manager->getRolesByBundle('protocol', 'protocol'),
      fn($r) => !in_array($r->id(), ['protocol-protocol-member', 'protocol-protocol-non-member'])
    );
  }

  /**
   * Returns communities the current user can manage, excluding those the target
   * user is already a member of.
   */
  protected function getAvailableCommunities(User $target): array {
    $current_user = User::load($this->currentUser()->id());
    $communities = [];

    if ($this->currentUser()->hasPermission('administer users')) {
      foreach (\Drupal::entityTypeManager()->getStorage('community')->loadMultiple() as $c) {
        if (!$c->getMembership($target)) {
          $communities[$c->id()] = $c;
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
        $c = $membership->getGroup();
        if ($c && !$c->getMembership($target)) {
          $communities[$c->id()] = $c;
        }
      }
    }

    uasort($communities, fn($a, $b) => strcmp($a->getName(), $b->getName()));
    return $communities;
  }

  /**
   * Given a community_selections array from step 1, returns the subset of
   * community IDs that had at least one role checked.
   */
  protected function getSelectedCommunityIds(array $selections): array {
    $ids = [];
    foreach ($selections as $cid => $values) {
      foreach ($values as $key => $val) {
        if ($key !== 'community_name' && !empty($val)) {
          $ids[] = $cid;
          break;
        }
      }
    }
    return $ids;
  }

  /**
   * Builds the protocol table data for step 2.
   *
   * Returns an array keyed by protocol ID, each value containing:
   *   'community_name', 'protocol_name', 'protocol' (entity).
   */
  protected function getProtocolsForCommunities(array $community_ids, User $target): array {
    $rows = [];
    $current_user = User::load($this->currentUser()->id());
    $is_admin = $this->currentUser()->hasPermission('administer users');

    // Collect protocols the current user can manage.
    $manageable_protocol_ids = [];
    if ($is_admin) {
      $manageable_protocol_ids = NULL; // NULL means all
    }
    else {
      foreach (Og::getMemberships($current_user) as $m) {
        if ($m->getGroupBundle() === 'protocol' &&
            ($m->hasPermission('administer group') || $m->hasPermission('manage members'))) {
          $manageable_protocol_ids[] = $m->getGroupId();
        }
      }
    }

    foreach ($community_ids as $cid) {
      $community = \Drupal::entityTypeManager()->getStorage('community')->load($cid);
      if (!$community) {
        continue;
      }
      foreach ($community->getProtocols() as $protocol) {
        // Skip if current user cannot manage this protocol.
        if ($manageable_protocol_ids !== NULL && !in_array($protocol->id(), $manageable_protocol_ids)) {
          continue;
        }
        // Skip if user is already a protocol member.
        if ($protocol->getMembership($target)) {
          continue;
        }
        $rows[$protocol->id()] = [
          'community_name' => $community->getName(),
          'protocol_name'  => $protocol->getName(),
          'protocol'       => $protocol,
        ];
      }
    }

    return $rows;
  }

  // ---------------------------------------------------------------------------
  // Form building
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, User $user = NULL) {
    if (!$user) {
      return $form;
    }

    $form_state->set('target_uid', $user->id());
    $step = $form_state->get('step') ?? 1;

    $form['user_info'] = [
      '#type'   => 'item',
      '#title'  => $this->t('Adding user'),
      '#markup' => $user->getDisplayName(),
    ];

    if ($step === 1) {
      return $this->buildStep1($form, $form_state, $user);
    }
    return $this->buildStep2($form, $form_state);
  }

  /**
   * Builds the community-assignment table (step 1).
   */
  protected function buildStep1(array $form, FormStateInterface $form_state, User $user): array {
    $communities = $this->getAvailableCommunities($user);
    $roles = $this->getCommunityRoles();

    if (empty($communities)) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('@user is already a member of all communities you manage.', [
          '@user' => $user->getDisplayName(),
        ]) . '</p>',
      ];
      return $form;
    }

    $header = [$this->t('Community')];
    foreach ($roles as $role) {
      $header[] = $role->getLabel();
    }

    $form['memberships'] = [
      '#type'   => 'table',
      '#header' => $header,
      '#empty'  => $this->t('No communities available.'),
    ];

    foreach ($communities as $cid => $community) {
      $form['memberships'][$cid]['community_name'] = [
        '#plain_text' => $community->getName(),
      ];
      foreach ($roles as $role) {
        $form['memberships'][$cid][$role->id()] = [
          '#type'          => 'checkbox',
          '#title'         => $role->getLabel(),
          '#title_display' => 'invisible',
          // Restore previously entered values if the user goes Back.
          '#default_value' => $form_state->get(['community_selections', $cid, $role->id()]) ?? 0,
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Next: Assign protocol roles'),
      '#submit' => ['::submitStep1'],
    ];

    return $form;
  }

  /**
   * Builds the protocol-assignment table (step 2).
   */
  protected function buildStep2(array $form, FormStateInterface $form_state): array {
    $protocol_rows = $form_state->get('protocol_rows');
    $roles = $this->getProtocolRoles();

    $header = [$this->t('Community'), $this->t('Protocol')];
    foreach ($roles as $role) {
      $header[] = $role->getLabel();
    }

    $form['protocol_memberships'] = [
      '#type'   => 'table',
      '#header' => $header,
      '#empty'  => $this->t('No protocols available for the selected communities.'),
    ];

    foreach ($protocol_rows as $pid => $row) {
      $form['protocol_memberships'][$pid]['community_name'] = [
        '#plain_text' => $row['community_name'],
      ];
      $form['protocol_memberships'][$pid]['protocol_name'] = [
        '#plain_text' => $row['protocol_name'],
      ];
      foreach ($roles as $role) {
        $form['protocol_memberships'][$pid][$role->id()] = [
          '#type'          => 'checkbox',
          '#title'         => $role->getLabel(),
          '#title_display' => 'invisible',
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save'),
    ];
    $form['actions']['back'] = [
      '#type'                    => 'submit',
      '#value'                   => $this->t('Back'),
      '#submit'                  => ['::goBack'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  // ---------------------------------------------------------------------------
  // Submit handlers
  // ---------------------------------------------------------------------------

  /**
   * Step 1 submit: decide whether to proceed to step 2 or save immediately.
   */
  public function submitStep1(array &$form, FormStateInterface $form_state) {
    $uid  = $form_state->get('target_uid');
    $user = User::load($uid);
    $selections = $form_state->getValue('memberships') ?? [];

    // Store so step 2 can restore values if user goes Back.
    $form_state->set('community_selections', $selections);

    $selected_cids = $this->getSelectedCommunityIds($selections);

    if (empty($selected_cids)) {
      // Nothing selected – warn and stay on step 1.
      $this->messenger()->addWarning($this->t('Please select at least one community role.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    // Look for protocols in the selected communities.
    $protocol_rows = $this->getProtocolsForCommunities($selected_cids, $user);

    if (!empty($protocol_rows)) {
      // Proceed to step 2.
      $form_state->set('protocol_rows', $protocol_rows);
      $form_state->set('step', 2);
      $form_state->setRebuild(TRUE);
    }
    else {
      // No protocols available – save community memberships now and finish.
      $this->saveCommunityMemberships($user, $selections);
      $form_state->setRedirect('view.mukurtu_people.page_1');
    }
  }

  /**
   * Back button: return to step 1 without saving anything.
   */
  public function goBack(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc} – Final save (step 2 submit).
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid  = $form_state->get('target_uid');
    $user = User::load($uid);

    // Save community memberships from step 1.
    $community_selections = $form_state->get('community_selections') ?? [];
    $this->saveCommunityMemberships($user, $community_selections);

    // Save protocol memberships from step 2.
    $protocol_selections = $form_state->getValue('protocol_memberships') ?? [];
    $this->saveProtocolMemberships($user, $protocol_selections);

    $form_state->setRedirect('view.mukurtu_people.page_1');
  }

  // ---------------------------------------------------------------------------
  // Persistence helpers
  // ---------------------------------------------------------------------------

  /**
   * Adds the user to each community for which a role was selected.
   */
  protected function saveCommunityMemberships(User $user, array $selections): void {
    $roles = $this->getCommunityRoles();
    $added = [];

    foreach ($selections as $cid => $values) {
      $selected_roles = [];
      foreach ($roles as $role) {
        if (!empty($values[$role->id()])) {
          $selected_roles[] = str_replace('community-community-', '', $role->id());
        }
      }
      if (empty($selected_roles)) {
        continue;
      }
      $community = \Drupal::entityTypeManager()->getStorage('community')->load($cid);
      if ($community) {
        $community->addMember($user, $selected_roles);
        $added[] = $community->getName();
      }
    }

    if (!empty($added)) {
      $this->messenger()->addStatus($this->t('@user added to communities: @list.', [
        '@user' => $user->getDisplayName(),
        '@list' => implode(', ', $added),
      ]));
    }
  }

  /**
   * Adds the user to each protocol for which a role was selected.
   */
  protected function saveProtocolMemberships(User $user, array $selections): void {
    $roles = $this->getProtocolRoles();
    $added = [];

    foreach ($selections as $pid => $values) {
      $selected_roles = [];
      foreach ($roles as $role) {
        if (!empty($values[$role->id()])) {
          $selected_roles[] = str_replace('protocol-protocol-', '', $role->id());
        }
      }
      if (empty($selected_roles)) {
        continue;
      }
      $protocol = \Drupal::entityTypeManager()->getStorage('protocol')->load($pid);
      if ($protocol) {
        $protocol->addMember($user, $selected_roles);
        $added[] = $protocol->getName();
      }
    }

    if (!empty($added)) {
      $this->messenger()->addStatus($this->t('@user added to protocols: @list.', [
        '@user' => $user->getDisplayName(),
        '@list' => implode(', ', $added),
      ]));
    }
  }

}
