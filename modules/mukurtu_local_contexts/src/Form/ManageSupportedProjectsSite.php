<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;


/**
 * Provides a Local Contexts form.
 */
class ManageSupportedProjectsSite extends ManageSupportedProjectsBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_site_supported_projects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state,) {
    $supportedProjectManager = new LocalContextsSupportedProjectManager();
    $projects = $supportedProjectManager->getSiteSupportedProjects();

    // Set the properties needed for the base form to function.
    $form_state->set('projects', $projects);

    $form = parent::buildForm($form, $form_state);

    // Group-form specific changes.
    $add_url = Url::fromRoute('mukurtu_local_contexts.add_site_supported_project');
    $form['projects']['#caption'] = $projects ? $this->t('The following Local Contexts Projects are available to all users. To delete an unused project, check the box next to it and click the "Remove Selected Projects" button.') : NULL;
    $form['projects']['#empty'] = $this->t('No Local Contexts projects have been added yet. <a href=":url">Add a project</a>.', [
      ':url' => $add_url->toString(),
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No operation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $projects = $form_state->getValue('projects');
    $supportedProjectManager = new LocalContextsSupportedProjectManager();
    $projects = array_filter($projects);
    foreach ($projects as $id) {
      if ($projectToRemove = new LocalContextsProject($id)) {
        if (!$projectToRemove->inUse()) {
          $title = $projectToRemove->getTitle();
          $supportedProjectManager->removeSiteProject($id);
          $this->messenger()->addStatus($this->t('Removed project %project.', ['%project' => $title]));
        }
      }
    }
  }

}
