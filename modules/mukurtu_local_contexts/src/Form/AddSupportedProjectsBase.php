<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsApi;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Local Contexts form that can be used by both groups and sites.
 */
abstract class AddSupportedProjectsBase extends FormBase {

  /**
   * Constructs the form with dependencies.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $group = $form_state->get('group');

    // Populate the API key from the group or site settings if available.
    if (empty($api_key)) {
      if ($group) {
        $api_key = $group->get('field_local_contexts_api_key')->value;
      }
      else {
        $api_key = $this->config('mukurtu_local_contexts.settings')->get('site_api_key');
      }
    }

    // Populate the list of projects from the API.
    if ($api_key) {
      $lcApi = new LocalContextsApi();
      if ($lcApi->validateApiKey($api_key)) {
        $all_projects_response = $lcApi->makeMultipageRequest('/projects', $api_key);
        if (is_array($all_projects_response) && $lcApi->getErrorMessage() == '') {
          $all_projects = $all_projects_response;
        }
        unset($all_projects_response);
      }
    }

    $form['#tree'] = TRUE;

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#required' => TRUE,
      '#description' => $this->t('You can find this on the community settings page on the Local Contexts Hub.'),
    ];

    if (isset($all_projects)) {
      $form['api_key']['#type'] = 'value';
      $form['api_key']['#value'] = $api_key;

      $form['api_key_reset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Current API Key'),
        '#attributes' => ['class' => ['inline-form-item']],
        '#description' => $this->t('This API key is used to retrieve the list of projects shown below.'),
      ];
      $form['api_key_reset']['current'] = [
        '#type' => 'form_item',
        '#title' => $this->t('API Key'),
        '#markup' => '<span class="api-key-current">' . substr($api_key, 0, 10) . str_repeat('X', strlen($api_key) - 10) . '</span> ',
      ];
      $form['api_key_reset']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear API Key'),
        '#validate' => [],
        '#submit' => ['::resetApiKey'],
      ];

      $form['projects'] = [
        '#type' => 'tableselect',
        '#header' => [
          'title' => $this->t('Title'),
          'status' => $this->t('Status'),
          'project_id' => $this->t('Project ID'),
        ],
        '#caption' => NULL, // Set in sub-classes.
        '#empty' => $this->t('No Local Context projects are available to the provided API key. Check that your account has access to at least one Local Contexts account, and that projects have been set up within that account.'),
        '#js_select' => TRUE,
      ];

      $options = [];
      foreach ($all_projects as $project) {
        $id = $project['unique_id'];
        $existingProject = new LocalContextsProject($id);
        $options[$id] = [
          'title' => $project['title'],
          'status' => $existingProject->isValid() ? $this->t('Existing') : $this->t('Not imported'),
          'project_id' => $id,
        ];
      }
      $form['projects']['#options'] = $options;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if (!isset($all_projects)) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#validate' => ['::validateApiKey'],
        '#submit' => ['::submitApiKey'],
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import projects'),
        '#button_type' => 'primary',
        '#access' => isset($all_projects),
        // Disable the button if there are no projects to import.
        '#disabled' => empty($all_projects),
        '#validate' => ['::validateForm'],
        '#submit' => ['::submitForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $lcApi = new LocalContextsApi();
    $valid_key = $lcApi->validateApiKey($api_key);
    if (!$valid_key) {
      $error_message = $lcApi->getErrorMessage();
      $form_state->setErrorByName('api_key', $error_message);
    }
  }

  /**
   * Submit handler for the "Next" button that sets the API key.
   */
  public function submitApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $group = $form_state->get('group');

    // Save the API key in the group.
    if ($group) {
      $group->set('field_local_contexts_api_key', $api_key);
      $group->save();
    }
    else {
      // Save the API key in the site-wide settings.
      $this->configFactory()->getEditable('mukurtu_local_contexts.settings')->set('site_api_key', $api_key)->save();
    }

    // Once the API key is set, we can rebuild the form to show the projects.
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the reset API key button.
   */
  public function resetApiKey(array &$form, FormStateInterface $form_state) {
    $this->requestStack->getCurrentRequest()->getSession()->remove('mukurtu_local_contexts_api_key');
    $form_state->setValue('api_key', NULL);
    $group = $form_state->get('group');

    // Clear the API key in the group.
    if ($group) {
      $group->set('field_local_contexts_api_key', '');
      $group->save();
    }
    else {
      // Clear the API key in the site-wide settings.
      $this->configFactory()->getEditable('mukurtu_local_contexts.settings')->set('site_api_key', '')->save();
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  abstract public function submitForm(array &$form, FormStateInterface $form_state);

}
