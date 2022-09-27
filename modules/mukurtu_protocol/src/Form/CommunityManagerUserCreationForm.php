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
      if ($roleValue->getName() != "non-member") {
        $roles[$roleValue->getName()] = $this->t($roleValue->getLabel());
      }
    }

    // Get the communities that the user has the 'manage members' permission in.
    $communities = [];
    $communityMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'community');
    $managerMemberships = array_filter($communityMemberships, fn ($m) => $m->hasPermission('manage members'));
    $managerCommunities = array_map(fn ($m) => $m->getGroup(), $managerMemberships);

    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    foreach ($managerCommunities as $community) {
      $communities[$community->id()] = $community->getName();
    }

    // Put communities in ascending alphabetical order.
    asort($communities);

    // Build the form.
    $form['info'] = [
      '#markup' => $this->t("This web page allows administrators to register new users. Users' email addresses and usernames must be unique."),
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
