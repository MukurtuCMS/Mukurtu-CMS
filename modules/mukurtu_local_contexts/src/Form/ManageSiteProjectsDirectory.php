<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides a form to manage the site level local contexts projects directory.
 */
class ManageSiteProjectsDirectory extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_site_projects_directory';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $description = $this->config('mukurtu_local_contexts.settings')->get('mukurtu_local_contexts_manage_site_projects_directory_description') ?? '';
    $form['description'] = [
      '#title' => $this->t('Description'),
      '#description' => $this->t("Enter the description for the site local contexts project directory page."),
      '#default_value' => $description,
      '#type' => 'textarea',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

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
    $description = $form_state->getValue('description');
    $this->configFactory->getEditable('mukurtu_local_contexts.settings')->set('mukurtu_local_contexts_manage_site_projects_directory_description', $description)->save();
  }

}
