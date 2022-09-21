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
        $roles[$roleValue->getName()] = t($roleValue->getLabel());
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

    // Build the form.
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Username of the new user.'),
      '#default_value' => "",
    ];

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#description' => $this->t('Email of the new user.'),
      '#default_value' => "",
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
        '#title' => t($community),
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
      '#value' => $this->t('Submit'),
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
    $user->save();

    _user_mail_notify('register_admin_created', $user);

    $results = $results['table'];

    // Track communities and roles.
    foreach ($results as $id => $result) {
      $roles = [];
      $roleNames = [];

      $roles = array_filter($result['roles']);

      if (!empty($roles)) {
        foreach ($roles as $name => $label) {
          array_push($roleNames, $name);
        }
      }

      // Load the community entity.
      $entityTypeManager = \Drupal::service("entity_type.manager");
      $community = $entityTypeManager->getStorage('community')->load($id);

      // Add new user to community with their selected roles.
      $community->addMember($user, $roleNames);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('username');

    $result = \Drupal::entityQuery('user')
      ->condition('name', $username)
      ->accessCheck(FALSE)
      ->execute();

    // Entity query should return nothing.
    if ($result) {
      $form_state->setErrorByName('username', t('User already exists with that username.'));
    }

    $emailValidator = \Drupal::service("email.validator");
    $email = $form_state->getValue('email');

    if (!$emailValidator->isValid($email)) {
      $form_state->setErrorByName('email', t('Please enter a valid email address.'));
    }
  }
}
