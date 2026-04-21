<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\og\Og;

/**
 * Form controller for Community Manager user creation form.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityManagerUserCreationForm extends FormBase {

  // Have a place to save the communities in this entity.
  protected $communities;

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'community_manager_user_creation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Fetch the current user.
    $currentUser = User::load(\Drupal::currentUser()->id());

    // Fetch the role manager.
    $roleManager = \Drupal::service("og.role_manager");

    // Fetch community roles.
    $rolesRaw = $roleManager->getRolesByBundle('community', 'community');

    $roles = [];

    foreach ($rolesRaw as $roleKey => $roleValue) {
      // Do not include the 'non-member' and generic 'member' roles as options.
      // The correct 'member' role to include is the bundle-specific one,
      // 'community-member'.
      if ($roleValue->getName() != "non-member" && $roleValue->getName() != 'member') {
        $roles[$roleValue->getName()] = $this->t($roleValue->getLabel());
      }
    }

    // Fetch protocol roles (same filter pattern as community roles).
    $protocolRolesRaw = $roleManager->getRolesByBundle('protocol', 'protocol');
    $protocolRoles = [];
    foreach ($protocolRolesRaw as $roleValue) {
      if ($roleValue->getName() !== 'non-member' && $roleValue->getName() !== 'member') {
        $protocolRoles[$roleValue->getName()] = $this->t($roleValue->getLabel());
      }
    }

    // Get the communities that the user has the 'manage members' permission in.
    $communities = [];
    $communityMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'community');
    $managerMemberships = array_filter($communityMemberships, fn ($m) => $m->hasPermission('manage members'));
    $managerCommunities = array_filter(array_map(fn ($m) => $m->getGroup(), $managerMemberships));

    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    foreach ($managerCommunities as $community) {
      $communities[$community->id()] = $community->getName();
    }

    // Put communities in ascending alphabetical order.
    asort($communities);

    // Get protocols where the current user has 'manage members' permission.
    $protocolMemberships = array_filter(
      Og::getMemberships($currentUser),
      fn ($m) => $m->getGroupBundle() === 'protocol'
    );
    $stewardMemberships = array_filter($protocolMemberships, fn ($m) => $m->hasPermission('manage members'));
    $stewardProtocols = array_filter(array_map(fn ($m) => $m->getGroup(), $stewardMemberships));

    // Group protocols by parent community ID.
    $protocolsByCommunity = [];
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
    foreach ($stewardProtocols as $protocol) {
      foreach ($protocol->getCommunities() as $parentCommunity) {
        $protocolsByCommunity[$parentCommunity->id()][$protocol->id()] = $protocol;
    // Get protocols grouped by community, for the user's managed communities.
    $protocolStorage = \Drupal::entityTypeManager()->getStorage('protocol');
    $protocolsByCommunity = [];
    foreach ($managerCommunities as $community) {
      $communityProtocolIds = $protocolStorage->getQuery()
        ->condition('field_communities', $community->id())
        ->accessCheck(FALSE)
        ->execute();
      $communityProtocols = [];
      foreach ($protocolStorage->loadMultiple($communityProtocolIds) as $protocol) {
        $communityProtocols[$protocol->id()] = $protocol->getName();
      }
      asort($communityProtocols);
      if (!empty($communityProtocols)) {
        $protocolsByCommunity[$community->id()] = [
          'label' => $community->getName(),
          'protocols' => $communityProtocols,
        ];
      }
    }

    // Build the form.
    $form['info'] = [
      '#markup' => $this->t("This web page allows community administrators to register new users. Users' email addresses and usernames must be unique."),
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('The email address is not made public. It will only be used to contact the user about their account or for opted-in notifications.'),
      '#default_value' => "",
      '#required' => FALSE,
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
      '#default_value' => "",
      '#required' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password_confirm',
      '#description' => $this->t('Leave blank to allow the user to set their own password via a password reset email.'),
      '#required' => FALSE,
    ];

    $form['field_display_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display Name'),
      '#description' => $this->t('Optional. If no display name is provided, your username will be displayed instead.'),
      '#default_value' => '',
      '#required' => FALSE,
    ];

    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [1 => $this->t('Active'), 0 => $this->t('Blocked')],
      '#default_value' => 1,
    ];

    $form['notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify user of new account'),
      '#default_value' => 1,
    ];

    $form['membership'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($communities as $communityId => $communityName) {
      $form['membership'][$communityId] = [
        '#type' => 'details',
        '#title' => $this->t($communityName),
        '#open' => FALSE,
      ];

      $form['membership'][$communityId]['community_roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Community Roles'),
        '#options' => $roles,
      ];

      if (!empty($protocolsByCommunity[$communityId])) {
        $communityMemberSelector = ':input[name="membership[' . $communityId . '][community_roles][community_member]"]';

        $form['membership'][$communityId]['protocols'] = [
          '#type' => 'container',
          '#tree' => TRUE,
          '#states' => ['visible' => [$communityMemberSelector => ['checked' => TRUE]]],
        ];

        /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
        foreach ($protocolsByCommunity[$communityId] as $protocolId => $protocol) {
          $form['membership'][$communityId]['protocols'][$protocolId] = [
            '#type' => 'details',
            '#title' => $this->t($protocol->getName()),
            '#open' => FALSE,
          ];
          $form['membership'][$communityId]['protocols'][$protocolId]['protocol_roles'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Protocol Roles'),
            '#options' => $protocolRoles,
          ];
        }
      }
    }

    $form['notify'] = [
      '#type' => 'details',
      '#title' => $this->t('Notify other users'),
      '#description' => $this->t('Optionally notify users about this new account.'),
      '#open' => FALSE,
      '#attached' => ['library' => ['mukurtu_core/notify-form']],
    ];

    $form['notify']['notify_all_managers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify all Mukurtu Managers'),
      '#default_value' => FALSE,
    ];

    if (!empty($communities)) {
      $form['notify']['notify_communities'] = [
        '#type' => 'checkboxes',
        // Use the visible legend rendered by #type => 'checkboxes' as the
        // group label so it is properly associated via <fieldset>/<legend>.
        '#title' => $this->t('Notify all community managers in the following communities:'),
        '#options' => $communities,
        '#required' => FALSE,
        '#attributes' => ['class' => ['notify-form-checkboxes'], 'style' => 'margin-inline-start: .5rem;'],
      ];
    }

    if (!empty($protocolsByCommunity)) {
      $form['notify']['notify_protocols'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['notify-protocols-wrapper']],
      ];
      $form['notify']['notify_protocols']['title'] = [
        // Use <p> instead of <label> — this text labels the group visually
        // but is not associated with a specific control. #title is not rendered
        // on #type => 'container' elements, so #markup is required here.
        '#markup' => '<p class="fieldset__label fieldset__label--group">' . $this->t('Notify all protocol stewards in the following protocols:') . '</p>',
      ];
      foreach ($protocolsByCommunity as $communityId => $data) {
        $form['notify']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
          '#attributes' => ['class' => ['notify-form-checkboxes'], 'style' => 'margin-inline-start: .5rem;'],
        ];
      }
    }

    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;

    $form['notify']['notify_users'] = [
      '#type' => 'container',
      // aria-live="polite" announces new fields to screen readers when
      // "Add another user" fires. <p> replaces the unassociated <label>.
      '#prefix' => '<div id="notify-users-wrapper" aria-live="polite"><p class="fieldset__label fieldset__label--group">' . $this->t('Notify specific users:') . '</p>',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $notifyUserCount; $i++) {
      $form['notify']['notify_users']['user_' . $i] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        // Numbered labels give screen reader users positional context when
        // multiple fields exist.
        '#title' => $this->t('User @num', ['@num' => $i + 1]),
        '#title_display' => 'invisible',
        '#selection_handler' => 'mukurtu_manager_users',
        '#required' => FALSE,
      ];
    }

    $form['notify']['notify_users']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another user'),
      '#submit' => ['::addMoreNotifyUser'],
      '#ajax' => [
        'callback' => '::addMoreNotifyUserCallback',
        'wrapper' => 'notify-users-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create new account'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    $username = $values['username'];
    $email = $values['email'];

    $user = User::create();
    $user->setUsername($username);

    if (!empty($email)) {
      $user->setEmail($email);
    }

    if (!empty($values['field_display_name'])) {
      $user->set('field_display_name', $values['field_display_name']);
    }

    if (!empty($values['pass'])) {
      $user->setPassword($values['pass']);
    }

    if ((int) $values['status'] === 1) {
      $user->activate();
    }
    else {
      $user->block();
    }

    $user->save();

    if (!empty($values['notify']) && !empty($email)) {
      _user_mail_notify('status_activated', $user);
    }

    $entityTypeManager = \Drupal::service("entity_type.manager");

    // Process community memberships and roles.
    foreach ($values['membership'] as $communityId => $communityData) {
      $communityRoles = array_keys(array_filter($communityData['community_roles']));

      if (!empty($communityRoles)) {
        /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
        $community = $entityTypeManager->getStorage('community')->load($communityId);
        $community->addMember($user, $communityRoles);
      }

      // Process protocol memberships under this community.
      foreach ($communityData['protocols'] ?? [] as $protocolId => $protocolData) {
        $protocolRoles = array_keys(array_filter($protocolData['protocol_roles']));

        if (!empty($protocolRoles)) {
          /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
          $protocol = $entityTypeManager->getStorage('protocol')->load($protocolId);
          $protocol->addMember($user, $protocolRoles);
        }
      }
    }

    $this->messenger()->addMessage($this->t('The new user account <a href=":url">%name</a> has been created.', [':url' => $user->toUrl()->toString(), '%name' => $user->getAccountName()]));

    if (empty($email)) {
      $this->messenger()->addMessage($this->t('No email address was provided, so a notification email has not been sent to the new user.'));
    }

    // Resolve individual user selections.
    $notifyUids = [];
    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;
    for ($i = 0; $i < $notifyUserCount; $i++) {
      $uid = $form_state->getValue(['notify_users', 'user_' . $i]);
      if (!empty($uid)) {
        $notifyUids[] = (int) $uid;
      }
    }

    // Resolve group selections.
    $allManagers = (bool) $form_state->getValue('notify_all_managers');
    $communityIds = array_keys(array_filter($form_state->getValue('notify_communities') ?? []));
    $protocolIds = [];
    foreach ($form_state->getValue('notify_protocols') ?? [] as $groupValues) {
      if (is_array($groupValues)) {
        $protocolIds = array_merge($protocolIds, array_keys(array_filter($groupValues)));
      }
    }
    $protocolIds = array_unique($protocolIds);

    if (function_exists('mukurtu_notifications_resolve_notify_groups')) {
      $groupUids = mukurtu_notifications_resolve_notify_groups($allManagers, $communityIds, $protocolIds);
      $notifyUids = array_unique(array_merge($notifyUids, $groupUids));
    }

    if (!empty($notifyUids) && function_exists('mukurtu_notifications_notify_new_account_created')) {
      mukurtu_notifications_notify_new_account_created($user, $notifyUids);
    }
  }

  /**
   * AJAX submit handler to add another user autocomplete field.
   */
  public function addMoreNotifyUser(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('notify_user_count') ?? 1;
    $form_state->set('notify_user_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback to return the updated notify_users container.
   */
  public function addMoreNotifyUserCallback(array &$form, FormStateInterface $form_state): array {
    return $form['notify']['notify_users'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('username');
    $email = $form_state->getValue('email');

    // Check that username is unique.
    $result = \Drupal::entityQuery('user')
      ->condition('name', $username)
      ->accessCheck(FALSE)
      ->execute();

    // Entity query should return nothing.
    if ($result) {
      $form_state->setErrorByName('username', $this->t('The username @name is already taken.', ['@name' => $username]));
    }

    // Only validate email if one was provided.
    if (!empty($email)) {
      // Check that email is unique.
      $result = \Drupal::entityQuery('user')
        ->condition('mail', $email)
        ->accessCheck(FALSE)
        ->execute();

      if ($result) {
        $form_state->setErrorByName('email', $this->t('The email address @email is already taken.', ['@email' => $email]));
      }

      // Check that email is valid.
      $emailValidator = \Drupal::service("email.validator");
      if (!$emailValidator->isValid($email)) {
        $form_state->setErrorByName('email', $this->t('The email address @email is not valid.', ['@email' => $email]));
      }
    }
  }
}
