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
        $roles[$roleValue->getName()] = $roleValue->getLabel();
      }
    }

    // Fetch protocol roles (same filter pattern as community roles).
    $protocolRolesRaw = $roleManager->getRolesByBundle('protocol', 'protocol');
    $protocolRoles = [];
    foreach ($protocolRolesRaw as $roleValue) {
      if ($roleValue->getName() !== 'non-member' && $roleValue->getName() !== 'member') {
        $protocolRoles[$roleValue->getName()] = $roleValue->getLabel();
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

    // Group protocols (as objects) by parent community ID, for the membership section.
    $protocolsByCommunity = [];
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
    foreach ($stewardProtocols as $protocol) {
      foreach ($protocol->getCommunities() as $parentCommunity) {
        $protocolsByCommunity[$parentCommunity->id()][$protocol->id()] = $protocol;
      }
    }

    // Build a label+name-indexed version for the notify section.
    $protocolsByCommForNotify = [];
    foreach ($protocolsByCommunity as $communityId => $protocols) {
      $protocolNames = [];
      foreach ($protocols as $protocolId => $protocol) {
        $protocolNames[$protocolId] = $protocol->getName();
      }
      $protocolsByCommForNotify[$communityId] = [
        'label' => $communities[$communityId] ?? '',
        'protocols' => $protocolNames,
      ];
    }

    // Build the form.
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('Email addresses must be unique. The email address is not made public. It will only be used to contact the user about their account or for opted-in notifications.'),
      '#default_value' => "",
      '#required' => FALSE,
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Usernames must be unique. Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
      '#default_value' => "",
      '#required' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password_confirm',
      '#title' => $this->t('Password'),
      '#title_display' => 'invisible',
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

    $form['notify_others'] = [
      '#type' => 'details',
      '#title' => $this->t('Notify other users of new account'),
      '#description' => $this->t('You can choose to notify other users about the creation of this new account. This is useful if you think the user may need to be enrolled in additional communities and/or protocols. If you choose to notify other users, they will receive an email with the new account username and a link to the user profile.'),
      '#open' => FALSE,
      '#attached' => ['library' => ['mukurtu_core/notify-form']],
    ];

    if (!empty($communities)) {
      $form['notify_others']['notify_communities'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Notify all community managers in the following communities:'),
        '#options' => $communities,
        '#required' => FALSE,
        '#attributes' => ['class' => ['notify-form-checkboxes']],
      ];
    }

    if (!empty($protocolsByCommForNotify)) {
      $protocols_label_id = 'notify-protocols-label';
      $form['notify_others']['notify_protocols'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['notify-protocols-wrapper'],
          'role' => 'group',
          'aria-labelledby' => $protocols_label_id,
        ],
      ];
      $form['notify_others']['notify_protocols']['title'] = [
        '#markup' => '<p id="' . $protocols_label_id . '" class="fieldset__label fieldset__label--group">' . $this->t('Notify all protocol stewards in the following protocols:') . '</p>',
      ];
      foreach ($protocolsByCommForNotify as $communityId => $data) {
        $form['notify_others']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
          '#attributes' => ['class' => ['notify-form-checkboxes']],
        ];
      }
    }

    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;
    $users_label_id = 'notify-users-label';

    $form['notify_others']['notify_users'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="notify-users-wrapper" role="group" aria-labelledby="' . $users_label_id . '" aria-live="polite"><p id="' . $users_label_id . '" class="fieldset__label fieldset__label--group">' . $this->t('Notify specific users:') . '</p>',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $notifyUserCount; $i++) {
      $form['notify_others']['notify_users']['user_' . $i] = [
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

    $form['notify_others']['notify_users']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another user'),
      '#submit' => ['::addMoreNotifyUser'],
      '#ajax' => [
        'callback' => '::addMoreNotifyUserCallback',
        'wrapper' => 'notify-users-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['membership'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Community and Protocol Membership'),
      '#tree' => TRUE,
    ];

    foreach ($communities as $communityId => $communityName) {
      $form['membership'][$communityId] = [
        '#type' => 'details',
        '#title' => $communityName,
        '#open' => TRUE,
      ];

      $form['membership'][$communityId]['community_roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Community Roles'),
        '#options' => $roles,
      ];

      if (!empty($protocolsByCommunity[$communityId])) {
        $statesConditions = [];
        foreach (array_keys($roles) as $roleName) {
          $statesConditions[] = [':input[name="membership[' . $communityId . '][community_roles][' . $roleName . ']"]' => ['checked' => TRUE]];
        }

        $form['membership'][$communityId]['protocols'] = [
          '#type' => 'container',
          '#tree' => TRUE,
          '#states' => ['visible' => $statesConditions],
          '#prefix' => '<div aria-live="polite">',
          '#suffix' => '</div>',
        ];

        $form['membership'][$communityId]['protocols']['hint'] = [
          '#markup' => '<p>' . $this->t('Select one or more protocol roles below.') . '</p>',
        ];

        /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
        foreach ($protocolsByCommunity[$communityId] as $protocolId => $protocol) {
          $form['membership'][$communityId]['protocols'][$protocolId] = [
            '#type' => 'details',
            '#title' => $protocol->getName(),
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

    $form_state->set('communitiesWithProtocols', array_keys($protocolsByCommunity));

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

    // Explicitly store the authenticated role so it appears in the site's
    // user administration lists, which read from the stored roles field rather
    // than the computed getRoles() result.
    $user->get('roles')->appendItem(['target_id' => 'authenticated']);
    $user->save();

    if (!empty($values['notify']) && !empty($email) && (int) $values['status'] === 1) {
      _user_mail_notify('status_activated', $user);
    }

    $entityTypeManager = \Drupal::service("entity_type.manager");

    // Rebuild the current user's authorized community and protocol IDs to
    // guard against crafted POST requests targeting unauthorized entities.
    $currentUser = User::load(\Drupal::currentUser()->id());
    $communityMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'community');
    $managerMemberships = array_filter($communityMemberships, fn ($m) => $m->hasPermission('manage members'));
    $authorizedCommunityIds = array_values(array_filter(array_map(fn ($m) => $m->getGroupId(), $managerMemberships)));

    $protocolMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'protocol');
    $stewardMemberships = array_filter($protocolMemberships, fn ($m) => $m->hasPermission('manage members'));
    $authorizedProtocolIds = array_values(array_filter(array_map(fn ($m) => $m->getGroupId(), $stewardMemberships)));

    // Process community memberships and roles.
    try {
      foreach ($values['membership'] as $communityId => $communityData) {
        if (!in_array($communityId, $authorizedCommunityIds)) {
          continue;
        }

        $communityRoles = array_keys(array_filter($communityData['community_roles']));

        if (!empty($communityRoles)) {
          /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
          $community = $entityTypeManager->getStorage('community')->load($communityId);
          if (!$community) {
            continue;
          }
          $community->addMember($user, $communityRoles);

          // Process protocol memberships under this community. Protocol membership
          // requires community membership first, so this is intentionally nested.
          foreach ($communityData['protocols'] ?? [] as $protocolId => $protocolData) {
            if (!in_array($protocolId, $authorizedProtocolIds)) {
              continue;
            }

            $protocolRoles = array_keys(array_filter($protocolData['protocol_roles']));

            if (!empty($protocolRoles)) {
              /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
              $protocol = $entityTypeManager->getStorage('protocol')->load($protocolId);
              if (!$protocol) {
                continue;
              }
              $protocol->addMember($user, $protocolRoles);
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('mukurtu_protocol')->error('Error assigning memberships for new user @name: @message', [
        '@name' => $user->getAccountName(),
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addWarning($this->t('The account was created but some membership assignments may not have completed. Please review the memberships for <a href=":url">%name</a>.', [
        ':url' => $user->toUrl()->toString(),
        '%name' => $user->getAccountName(),
      ]));
    }

    $this->messenger()->addMessage($this->t('The new user account <a href=":url">%name</a> has been created.', [':url' => $user->toUrl()->toString(), '%name' => $user->getAccountName()]));

    if (empty($email)) {
      $this->messenger()->addMessage($this->t('No email address was provided, so a notification email has not been sent to the new user.'));
    }

    $notifyUids = mukurtu_notifications_extract_notify_uids($form_state);
    if (!empty($notifyUids)) {
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
    return $form['notify_others']['notify_users'];
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

    $membership = $form_state->getValue('membership') ?? [];

    // Require at least one community role to be selected.
    $hasAnyCommunityRole = FALSE;
    foreach ($membership as $communityData) {
      if (!empty(array_filter($communityData['community_roles'] ?? []))) {
        $hasAnyCommunityRole = TRUE;
        break;
      }
    }
    if (!$hasAnyCommunityRole) {
      $form_state->setError($form['membership'], $this->t('Please assign the new user at least one community role.'));
    }

    // For each community where the user is being given a role AND protocols
    // exist, require at least one protocol role to be selected.
    $communitiesWithProtocols = $form_state->get('communitiesWithProtocols') ?? [];
    foreach ($communitiesWithProtocols as $communityId) {
      $communityData = $membership[$communityId] ?? [];
      if (empty(array_filter($communityData['community_roles'] ?? []))) {
        // User isn't being added to this community — no protocol role required.
        continue;
      }
      $hasProtocolRole = FALSE;
      foreach ($communityData['protocols'] ?? [] as $protocolData) {
        if (!empty(array_filter($protocolData['protocol_roles'] ?? []))) {
          $hasProtocolRole = TRUE;
          break;
        }
      }
      if (!$hasProtocolRole) {
        $communityName = $form['membership'][$communityId]['#title'] ?? $communityId;
        $form_state->setError($form['membership'][$communityId], $this->t('Please assign the new user at least one protocol role in %community.', ['%community' => $communityName]));
      }
    }
  }
}
