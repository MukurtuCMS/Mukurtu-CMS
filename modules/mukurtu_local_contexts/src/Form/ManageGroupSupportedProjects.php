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
class ManageGroupSupportedProjects extends FormBase {
  protected $supportedProjectManager;

  public function __construct() {
    $this->supportedProjectManager = new LocalContextsSupportedProjectManager();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_group_supported_projects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $group = NULL) {
    $form_state->set('group', $group);
    $projects = $this->supportedProjectManager->getGroupSupportedProjects($group);

    $form['add_project_link'] = [
      '#title' => $this->t('Add Project'),
      '#type' => 'link',
      '#url' => \Drupal\Core\Url::fromRoute("mukurtu_local_contexts.add_{$group->getEntityTypeId()}_supported_project", ['group' => $group->id()]),
    ];

    if (!empty($projects)) {
      $form['projects'] = array(
        '#type' => 'table',
        '#caption' => $this->t('Local Contexts Projects available for members of %group', ['%group' => $group->getName()]),
        '#header' => array(
          '',
          $this
            ->t('Title'),
          $this
            ->t('Project ID'),
        ),
      );
      foreach ($projects as $id => $project) {
        if ($project = new LocalContextsProject($id)) {
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
    } else {
      $form['empty'] = [
        '#type' => 'processed_text',
        '#text' => $this->t('No projects have been added.')
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
    if ($group = $form_state->get('group')) {
      $projects = $form_state->getValue('projects');
      foreach ($projects as $id => $project) {
        if ($project['selected'] === "1") {

          if ($projectToRemove = new LocalContextsProject($id)) {
            if (!$projectToRemove->inUse()) {
              $title = $projectToRemove->getTitle();
              $this->supportedProjectManager->removeGroupProject($group, $id);
              $this->messenger()->addStatus($this->t('Removed project %project', ['%project' => $title]));
            }
          }
        }
      }
    }
  }

}
