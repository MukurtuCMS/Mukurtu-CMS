<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides a Local Contexts form.
 */
class AddGroupSupportedProject extends FormBase {
  protected $supportedProjectManager;
  protected $groupSupportedProjects;

  public function __construct() {
    $this->supportedProjectManager = new LocalContextsSupportedProjectManager();
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
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $group = NULL) {
    $this->groupSupportedProjects = array_keys($this->supportedProjectManager->getGroupSupportedProjects($group));
    $form_state->set('group', $group);
    $form['project_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project ID'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Project'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (in_array($form_state->getValue('project_id'), $this->groupSupportedProjects)) {
      $form_state->setErrorByName('name', $this->t('This project is already in use.'));
      return;
    }
    if (mb_strlen($form_state->getValue('project_id')) != 36) {
      $form_state->setErrorByName('name', $this->t('ID must be in valid UUID format'));
      return;
    }

    $id = mb_strtolower($form_state->getValue('project_id'));
    $project = new LocalContextsProject($id);
    if (!$project->isValid()) {
      $form_state->setErrorByName('name', $this->t('Could not find the project ID on the Local Contexts Hub.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($group = $form_state->get('group')) {
      $supportedProjectManager = new LocalContextsSupportedProjectManager();

      $id = mb_strtolower($form_state->getValue('project_id'));
      $supportedProjectManager->addGroupProject($group, $id);

      $this->messenger()->addStatus($this->t('The project has been added.'));
      $form_state->setRedirect("mukurtu_local_contexts.manage_{$group->getEntityTypeId()}_supported_projects", ['group' => $group->id()]);
    }
  }

}
