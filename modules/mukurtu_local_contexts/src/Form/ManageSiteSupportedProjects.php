<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;


/**
 * Provides a Local Contexts form.
 */
class ManageSiteSupportedProjects extends FormBase {
  protected $supportedProjectManager;

  public function __construct() {
    $this->supportedProjectManager = new LocalContextsSupportedProjectManager();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_site_supported_projects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $projects = $this->supportedProjectManager->getSiteSupportedProjects();

    if (!empty($projects)) {
      $form['projects'] = array(
        '#type' => 'table',
        '#caption' => $this->t('The following Local Contexts Projects are available to all users.'),
        '#header' => array(
          '',
          $this->t('Title'),
          $this->t('Project ID'),
        ),
      );
      foreach ($projects as $id => $project) {
        $project = new LocalContextsProject($id);
        if ($project->isValid()) {
          $in_use = $project->inUse();
          $form['projects'][$id]['selected'] = [
            '#type' => 'checkbox',
            '#description' => $in_use ? $this->t('Project is in use and cannot be removed') : '',
            '#disabled' => $in_use,
          ];
          $form['projects'][$id]['title'] = [
            '#type' => 'processed_text',
            '#text' => $project->getTitle(),
          ];
          $form['projects'][$id]['project_id'] = [
            '#type' => 'processed_text',
            '#text' => $project->id(),
            '#value' => $project->id(),
          ];
        }
      }

      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove Selected Projects'),
      ];
    }
    else {
      $add_url = Url::fromRoute('mukurtu_local_contexts.add_site_supported_project');
      $form['empty'] = [
        '#markup' => $this->t('No site-wide Local Contexts projects have been added yet. <a href=":url">Add a project</a>.', [
          ':url' => $add_url->toString(),
        ]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $projects = $form_state->getValue('projects');
    foreach ($projects as $id => $project) {
      if ($project['selected'] === "1") {
        if ($projectToRemove = new LocalContextsProject($id)) {
          if (!$projectToRemove->inUse()) {
            $title = $projectToRemove->getTitle();
            $this->supportedProjectManager->removeSiteProject($id);
            $this->messenger()->addStatus($this->t('Removed project %project', ['%project' => $title]));
          }
        }
      }
    }
  }

}
