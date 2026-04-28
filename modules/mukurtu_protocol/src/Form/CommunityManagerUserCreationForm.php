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

    // Fetch the roles.
    $roleManager = \Drupal::service("og.role_manager");
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
      '#title' => $this->t('Email'),
      '#description' => $this->t('A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.'),
      '#default_value' => "",
      '#required' => TRUE,
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
      '#default_value' => "",
      '#required' => TRUE,
    ];

    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Select communities and roles for the new user.'),
      '#header' => [
        $this->t('Communities'),
        $this->t('Roles'),
      ]
    ];

    // Make the data for checkboxes in the form.
    foreach ($communities as $id => $community) {
      $form['table'][$id]['communities'] = [
        '#type' => 'item',
        '#title' => $this->t($community),
      ];
      $form['table'][$id]['roles'] = [
        '#type' => 'checkboxes',
        '#options' => $roles,
      ];
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
        '#title' => $this->t('Notify all community managers in the following communities:'),
        '#options' => $communities,
        '#required' => FALSE,
        '#attributes' => ['class' => ['notify-form-checkboxes']],
      ];
    }

    if (!empty($protocolsByCommunity)) {
      $protocols_label_id = 'notify-protocols-label';
      $form['notify']['notify_protocols'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['notify-protocols-wrapper'],
          'role' => 'group',
          'aria-labelledby' => $protocols_label_id,
        ],
      ];
      $form['notify']['notify_protocols']['title'] = [
        '#markup' => '<p id="' . $protocols_label_id . '" class="fieldset__label fieldset__label--group">' . $this->t('Notify all protocol stewards in the following protocols:') . '</p>',
      ];
      foreach ($protocolsByCommunity as $communityId => $data) {
        $form['notify']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
          '#attributes' => ['class' => ['notify-form-checkboxes']],
        ];
      }
    }

    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;
    $users_label_id = 'notify-users-label';

    $form['notify']['notify_users'] = [
      '#type' => 'container',
      '#prefix' => '<div id="notify-users-wrapper" role="group" aria-labelledby="' . $users_label_id . '" aria-live="polite"><p id="' . $users_label_id . '" class="fieldset__label fieldset__label--group">' . $this->t('Notify specific users:') . '</p>',
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
    $results = $form_state->getValues();
    $username = $results['username'];
    $email = $results['email'];

    $user = User::create();
    $user->setUsername($username);
    $user->setEmail($email);
    $user->activate();
    $user->save();

    _user_mail_notify('status_activated', $user);

    $results = $results['table'];

    $entityTypeManager = \Drupal::service("entity_type.manager");

    // Track communities and roles.
    foreach ($results as $id => $result) {
      $roles = [];
      $roleNames = [];

      $roles = array_filter($result['roles']);

      if (!empty($roles)) {
        foreach ($roles as $name => $label) {
          array_push($roleNames, $name);
        }

        // Load the community entity.
        $community = $entityTypeManager->getStorage('community')->load($id);

        // Add new user to community with their selected roles.
        $community->addMember($user, $roleNames);
      }
    }

    $this->messenger()->addMessage($this->t('A welcome message with further instructions has been emailed to the new user <a href=":url">%name</a>.', [':url' => $user->toUrl()->toString(), '%name' => $user->getAccountName()]));

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
    $email = $form_state->getValue('email');

    if (!$emailValidator->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Please enter an email address.'));
    }
  }
}
