<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\user\Entity\User;

/**
 * Two-step form to enrol a user in a community and its child protocols.
 *
 * Step 1 - user selection and community role assignment.
 * Step 2 - protocol assignment table (only shown when the community has child
 *           protocols; skipped automatically otherwise).
 *
 * This is the community-centric counterpart of AddUserToCommunityForm:
 * the community is known from the route parameter and the user is selected
 * in the form.
 */
class AddMemberToCommunityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_add_member_to_community_form';
  }

  /**
   * Route access callback.
   */
  public static function access(AccountInterface $account, Community $group): AccessResult {
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    $user = User::load($account->id());
    foreach (Og::getMemberships($user) as $membership) {
      if ($membership->getGroupBundle() === 'community'
          && (int) $membership->getGroupId() === (int) $group->id()
          && ($membership->hasPermission('administer group') || $membership->hasPermission('manage members'))) {
        return AccessResult::allowed()->cachePerUser()->addCacheableDependency($group);
      }
    }
    return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($group);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the community OG roles (excluding member / non-member).
   */
  protected function getCommunityRoles(): array {
    $role_manager = \Drupal::service('og.role_manager');
    $roles = array_filter(
      $role_manager->getRolesByBundle('community', 'community'),
      fn($r) => !in_array($r->id(), ['community-community-member', 'community-community-non-member'])
    );
    uasort($roles, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    return $roles;
  }

  /**
   * Returns the protocol OG roles (excluding member / non-member), sorted by weight.
   */
  protected function getProtocolRoles(): array {
    $role_manager = \Drupal::service('og.role_manager');
    $roles = array_filter(
      $role_manager->getRolesByBundle('protocol', 'protocol'),
      fn($r) => !in_array($r->id(), ['protocol-protocol-member', 'protocol-protocol-non-member'])
    );
    uasort($roles, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    return $roles;
  }

  /**
   * Builds the protocol table rows for a given community and target user.
   *
   * Returns an array keyed by protocol ID, each value containing:
   *   'community_name', 'protocol_name', 'protocol' (entity).
   */
  protected function getProtocolsForCommunity(Community $community, User $target): array {
    $rows = [];
    $current_user = User::load($this->currentUser()->id());
    $is_admin = $this->currentUser()->hasPermission('administer users');

    $manageable_protocol_ids = [];
    if ($is_admin) {
      $manageable_protocol_ids = NULL; // NULL means all
    }
    else {
      foreach (Og::getMemberships($current_user) as $m) {
        if ($m->getGroupBundle() === 'protocol'
            && ($m->hasPermission('administer group') || $m->hasPermission('manage members'))) {
          $manageable_protocol_ids[] = $m->getGroupId();
        }
      }
    }

    foreach ($community->getProtocols() as $protocol) {
      if ($manageable_protocol_ids !== NULL && !in_array($protocol->id(), $manageable_protocol_ids)) {
        continue;
      }
      if ($protocol->getMembership($target)) {
        continue;
      }
      $rows[$protocol->id()] = [
        'community_name' => $community->getName(),
        'protocol_name'  => $protocol->getName(),
        'protocol'       => $protocol,
      ];
    }

    return $rows;
  }

  // ---------------------------------------------------------------------------
  // Role description helpers
  // ---------------------------------------------------------------------------

  protected static function buildCommunityRoleDescriptions(): array {
    $roles = [
      'community_member'    => t('Community member'),
      'community_affiliate' => t('Community affiliate'),
      'community_manager'   => t('Community manager'),
    ];
    $descriptions = [
      'community_member'    => t('View the community page and be added to protocols within the community.'),
      'community_affiliate' => t('View the community page and be added to protocols within the community. This is a designation for community partners.'),
      'community_manager'   => t('Manage community membership and create new protocols. View the community page and be added to protocols within the community.'),
    ];
    $items = [];
    foreach ($descriptions as $role_id => $description) {
      $items[] = ['#markup' => '<strong>' . $roles[$role_id] . '</strong>: ' . $description];
    }
    return [
      '#type' => 'details',
      '#title' => t('Role descriptions'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['role-descriptions']],
      'list' => ['#theme' => 'item_list', '#items' => $items],
    ];
  }

  protected static function buildProtocolRoleDescriptions(): array {
    $roles = [
      'protocol_member'          => t('Protocol member'),
      'protocol_affiliate'       => t('Protocol affiliate'),
      'contributor'              => t('Contributor'),
      'curator'                  => t('Curator'),
      'language_contributor'     => t('Language contributor'),
      'language_steward'         => t('Language steward'),
      'community_record_steward' => t('Community record steward'),
      'protocol_steward'         => t('Protocol steward'),
    ];
    $descriptions = [
      'protocol_member'          => t('View content but cannot create or edit.'),
      'protocol_affiliate'       => t('View content but cannot create or edit. This is a designation for community partners that mirrors the community affiliate role.'),
      'contributor'              => t('Create, edit, and delete their own digital heritage items, person records, place records, and media assets.'),
      'curator'                  => t('Create, edit, and delete their own collections and media assets.'),
      'language_contributor'     => t('Create, edit, and delete their own dictionary words and word lists.'),
      'language_steward'         => t('Create, edit, and delete ALL dictionary words and word lists, and media assets.'),
      'community_record_steward' => t('Add community records to content, as well as edit and delete their community records.'),
      'protocol_steward'         => t('Manage protocol membership, create, edit, and delete ALL content and media assets, and manage Local Contexts projects.'),
    ];
    $items = [];
    foreach ($descriptions as $role_id => $description) {
      $items[] = ['#markup' => '<strong>' . $roles[$role_id] . '</strong>: ' . $description];
    }
    return [
      '#type' => 'details',
      '#title' => t('Role descriptions'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['role-descriptions']],
      'list' => ['#theme' => 'item_list', '#items' => $items],
    ];
  }

  // ---------------------------------------------------------------------------
  // Form building
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Community $group = NULL) {
    if (!$group) {
      return $form;
    }

    $form_state->set('community_id', $group->id());
    $step = $form_state->get('step') ?? 1;

    $form['community_info'] = [
      '#type'   => 'item',
      '#title'  => $this->t('Community'),
      '#markup' => $group->getName(),
    ];

    if ($step === 1) {
      return $this->buildStep1($form, $form_state, $group);
    }
    return $this->buildStep2($form, $form_state);
  }

  /**
   * Builds the user selection and community role table (step 1).
   */
  protected function buildStep1(array $form, FormStateInterface $form_state, Community $community): array {
    $roles = $this->getCommunityRoles();

    $form['user'] = [
      '#type'               => 'entity_autocomplete',
      '#title'              => $this->t('User'),
      '#target_type'        => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#required'           => TRUE,
      '#default_value'      => $form_state->get('selected_user') ? User::load($form_state->get('selected_user')) : NULL,
    ];

    $form['community_role_descriptions'] = static::buildCommunityRoleDescriptions();

    $header = [['data' => $this->t('Community'), 'scope' => 'col']];
    foreach ($roles as $role) {
      $header[] = ['data' => $role->getLabel(), 'scope' => 'col'];
    }

    $cid = $community->id();
    $form['memberships'] = [
      '#type'    => 'table',
      '#caption' => $this->t('Select community roles'),
      '#header'  => $header,
      '#empty'   => $this->t('No roles available.'),
    ];

    $form['memberships'][$cid]['community_name'] = [
      '#plain_text' => $community->getName(),
    ];
    foreach ($roles as $role) {
      $form['memberships'][$cid][$role->id()] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('@role for @community', [
          '@role'      => $role->getLabel(),
          '@community' => $community->getName(),
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $form_state->get(['community_selections', $cid, $role->id()]) ?? 0,
      ];
    }

    $form['#attached']['library'][] = 'mukurtu_protocol/membership-table';

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Next: Assign protocol roles'),
      '#submit' => ['::submitStep1'],
    ];

    return $form;
  }

  /**
   * Builds the protocol assignment table (step 2).
   */
  protected function buildStep2(array $form, FormStateInterface $form_state): array {
    $protocol_rows = $form_state->get('protocol_rows');
    $roles = $this->getProtocolRoles();

    $header = [
      ['data' => $this->t('Community'), 'scope' => 'col'],
      ['data' => $this->t('Protocol'), 'scope' => 'col'],
    ];
    foreach ($roles as $role) {
      $header[] = ['data' => $role->getLabel(), 'scope' => 'col'];
    }

    $form['protocol_role_descriptions'] = static::buildProtocolRoleDescriptions();

    $target_user = User::load($form_state->get('selected_user'));
    $form['protocol_memberships'] = [
      '#type'    => 'table',
      '#caption' => $this->t('Select protocol roles for @user', ['@user' => $target_user ? $target_user->getDisplayName() : '']),
      '#header'  => $header,
      '#empty'   => $this->t('No protocols available for this community.'),
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
          '#title'         => $this->t('@role for @protocol (@community)', [
            '@role'      => $role->getLabel(),
            '@protocol'  => $row['protocol_name'],
            '@community' => $row['community_name'],
          ]),
          '#title_display' => 'invisible',
        ];
      }
    }

    $form['#attached']['library'][] = 'mukurtu_protocol/membership-table';

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
   * Step 1 submit: validate, then proceed to step 2 or save immediately.
   */
  public function submitStep1(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->getValue('user');
    $user = $uid ? User::load($uid) : NULL;

    if (!$user) {
      $this->messenger()->addWarning($this->t('Please select a user.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $cid = $form_state->get('community_id');
    $community = \Drupal::entityTypeManager()->getStorage('community')->load($cid);

    // Check that the selected user is not already a community member.
    if ($community && $community->getMembership($user)) {
      $this->messenger()->addWarning($this->t('@user is already a member of this community.', [
        '@user' => $user->getDisplayName(),
      ]));
      $form_state->setRebuild(TRUE);
      return;
    }

    $selections = $form_state->getValue('memberships') ?? [];
    $has_role = FALSE;
    foreach ($this->getCommunityRoles() as $role) {
      if (!empty($selections[$cid][$role->id()])) {
        $has_role = TRUE;
        break;
      }
    }

    if (!$has_role) {
      $this->messenger()->addWarning($this->t('Please select at least one community role.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $form_state->set('selected_user', $uid);
    $form_state->set('community_selections', $selections);

    // Look for manageable protocols in this community.
    $protocol_rows = $community ? $this->getProtocolsForCommunity($community, $user) : [];

    if (!empty($protocol_rows)) {
      $form_state->set('protocol_rows', $protocol_rows);
      $form_state->set('step', 2);
      $form_state->setRebuild(TRUE);
    }
    else {
      $this->saveCommunityMemberships($user, $selections);
      $this->messenger()->addWarning($this->t('You are not a protocol steward of any protocols in this community and cannot assign protocol roles.'));
      $form_state->setRedirect('mukurtu_protocol.community_members_list', ['group' => $cid]);
    }
  }

  /**
   * Back button: return to step 1 without saving.
   */
  public function goBack(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc} - Final save (step 2 submit).
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid  = $form_state->get('selected_user');
    $user = User::load($uid);
    $cid  = $form_state->get('community_id');

    $community_selections = $form_state->get('community_selections') ?? [];
    $this->saveCommunityMemberships($user, $community_selections);

    $protocol_selections = $form_state->getValue('protocol_memberships') ?? [];
    $this->saveProtocolMemberships($user, $protocol_selections);

    $form_state->setRedirect('mukurtu_protocol.community_members_list', ['group' => $cid]);
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
