<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;

/**
 * Provides a Local Contexts form.
 */
abstract class ManageSupportedProjectsBaseOld extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $projects = $form_state->get('projects');

    $form['projects'] = [
      '#type' => 'tableselect',
      '#header' => [
        'title' => $this->t('Title'),
        'project_id' => $this->t('Project ID'),
      ],
      '#caption' => NULL, // Set in child classes.
      '#empty' => NULL, // Set in child classes.
      '#js_select' => TRUE,
    ];

    $options = [];
    foreach ($projects as $id => $project) {
      $project = new LocalContextsProject($id);
      if ($project->isValid()) {
        $in_use = $project->inUse();
        $options[$id] = [
          'title' => $project->getTitle(),
          'project_id' => $project->id(),
        ];
        // Normally we would not need to redefine the entire checkbox here,
        // but it is needed to set the disabled and description properties.
        $form['projects'][$id] = [
          '#type' => 'checkbox',
          '#disabled' => $in_use,
          '#attributes' => $in_use ? ['title' => $this->t('Project is in use and cannot be removed')] : [],
          '#return_value' => $id,
        ];
      }
    }
    $form['projects']['#options'] = $options;

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove Selected Projects'),
      '#access' => !empty($projects),
    ];

    return $form;
  }

}
