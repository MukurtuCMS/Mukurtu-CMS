<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;

/**
 * Provides a Local Contexts form for adding supported projects to the site.
 */
class AddSupportedProjectsSite extends AddSupportedProjectsBase {

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
    $form = parent::buildForm($form, $form_state);
    $form['projects']['#caption'] = $this->t('Select the Local Contexts projects you would like to add to the site. Existing projects can be selected to update their content.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_projects = $form_state->getValue('projects');
    $api_key = $form_state->getValue('api_key');

    $supportedProjectManager = new LocalContextsSupportedProjectManager();
    $project_count = 0;
    $last_project_title = '';
    $selected_projects = array_filter($selected_projects);
    foreach ($selected_projects as $id) {
      $project = new LocalContextsProject($id);
      $project->fetchFromHub($api_key);
      $supportedProjectManager->addSiteProject($id);
      $project_count++;
      $last_project_title = $project->getTitle();
    }

    $message = $this->formatPlural($project_count, 'The project @title has been added.', '@count projects have been added.', [
      '@title' => $last_project_title,
    ]);
    $this->messenger()->addStatus($message);
    $form_state->setRedirect('mukurtu_local_contexts.manage_site_supported_projects');
  }

}
