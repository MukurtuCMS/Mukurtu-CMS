<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides a Local Contexts form.
 */
class ManageSupportedProjectsGroupOld extends ManageSupportedProjectsBase {

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
    $supportedProjectManager = new LocalContextsSupportedProjectManager();
    $projects = $supportedProjectManager->getGroupSupportedProjects($group);

    // Set the properties needed for the base form to function.
    $form_state->set('projects', $projects);
    $form_state->set('group', $group);

    $form = parent::buildForm($form, $form_state);

    // Group-form specific changes.
    $add_url = Url::fromRoute("mukurtu_local_contexts.add_{$group->getEntityTypeId()}_supported_project", ['group' => $group->id()]);
    $form['projects']['#caption'] = $projects ? $this->t('The following Local Contexts Projects are available to members of %group. To delete an unused project, check the box next to it and click the "Remove Selected Projects" button.', ['%group' => $group->getName()]) : NULL;
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
    if ($group = $form_state->get('group')) {
      $projects = $form_state->getValue('projects');
      $supportedProjectManager = new LocalContextsSupportedProjectManager();
      $projects = array_filter($projects);
      foreach ($projects as $id) {
        if ($projectToRemove = new LocalContextsProject($id)) {
          if (!$projectToRemove->inUse()) {
            $title = $projectToRemove->getTitle();
            $supportedProjectManager->removeGroupProject($group, $id);
            $this->messenger()->addStatus($this->t('Removed project %project.', ['%project' => $title]));
          }
        }
      }
    }
  }

}
