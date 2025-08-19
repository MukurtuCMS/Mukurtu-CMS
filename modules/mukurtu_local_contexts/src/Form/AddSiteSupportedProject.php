<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsApi;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;

/**
 * Provides a Local Contexts form.
 */
class AddSiteSupportedProject extends FormBase {
  protected LocalContextsApi $lcApi;
  protected LocalContextsSupportedProjectManager $supportedProjectManager;
  protected array $siteSupportedProjects;

  public function __construct() {
    $this->lcApi = new LocalContextsApi();
    $this->supportedProjectManager = new LocalContextsSupportedProjectManager();
    $this->siteSupportedProjects = array_keys($this->supportedProjectManager->getSiteSupportedProjects());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_add_site_supported_project';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $api_key = $form_state->getvalue('api_key');
    $all_projects = $form_state->getTemporaryValue('all_projects');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#required' => TRUE,
      '#description' => $this->t('You can find this on the community settings page on the Local Contexts Hub.'),
      '#access' => empty($api_key),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#access' => empty($api_key),
      '#validate' => ['::validateApiKey'],
      '#submit' => ['::submitApiKey'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select projects'),
      '#access' => !empty($all_projects),
      '#id' => 'submit-button',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $response = $this->lcApi->makeRequest('/projects', $api_key);
    if ($error_message = $this->lcApi->getErrorMessage()) {
      $form_state->setErrorByName('api_key', $error_message);
    }
  }

  public function submitApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $all_projects = $this->lcApi->makeMultipageRequest('/projects', $api_key);
    if ($error_message = $this->lcApi->getErrorMessage()) {
      $form_state->setErrorByName('', $error_message);
    }
    else {
      $form_state->setTemporaryValue('all_projects', $all_projects);
    }
    $form_state->setRebuild();
  }

    /**
     * {@inheritdoc}
     */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    $project = new LocalContextsProject($id);
//    $project->fetchFromHub($api_key);
//    if (!$project->isValid()) {
//      $form_state->setErrorByName('name', $this->t('Could not find the project ID on the Local Contexts Hub.'));
//    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $supportedProjectManager = new LocalContextsSupportedProjectManager();

    $id = mb_strtolower($form_state->getValue('project_id'));
    $supportedProjectManager->addSiteProject($id);

    $this->messenger()->addStatus($this->t('The project has been added.'));
    $form_state->setRedirect('mukurtu_local_contexts.manage_site_supported_projects');
  }

}
