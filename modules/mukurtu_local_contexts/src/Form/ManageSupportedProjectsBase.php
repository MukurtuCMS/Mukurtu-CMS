<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Provides a Local Contexts form.
 */
abstract class ManageSupportedProjectsBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $projects = $form_state->get('projects');

    if (!empty($projects)) {
      $form['projects'] = array(
        '#type' => 'table',
        '#caption' => NULL, // Set in child classes.
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
      $form['empty'] = [
        '#markup' => NULL, // Set in child classes.
      ];
    }

    return $form;
  }

}
