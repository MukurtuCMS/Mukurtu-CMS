<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to manage the site level local contexts projects directory.
 */
class ManageProjectsDirectorySite extends FormBase {

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
    $config = $this->configFactory()->getEditable('mukurtu_local_contexts.settings');
    $form_state->setTemporaryValue('config', $config);

    $config_description = $config->get('site_projects_directory_description') ?? [];
    $description_text = $this->t('Shown on the site-wide <a href=":url">Local Contexts projects directory page</a> (only when at least one Local Contexts project has been added).', [
      ':url' => Url::fromRoute('mukurtu_local_contexts.site_project_directory')->toString(),
    ]);
    $description_value = $config_description['value'] ?? '';
    $description_format = $config_description['format'] ?? 'basic_html';

    $allowed_formats = ['basic_html', 'full_html'];
    $form['description'] = [
      '#title' => $this->t('Description'),
      '#description' => $description_text,
      '#default_value' => $description_value,
      '#type' => 'text_format',
      '#format' => $description_format,
      '#allowed_formats' => $allowed_formats,
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
    // No operation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $form_state->getTemporaryValue('config');
    $this->messenger()->addMessage($this->t('The site-wide Local Contexts project directory page has been updated.'));
    $description = $form_state->getValue('description');
    $config->set('site_projects_directory_description', $description)->save();
  }

}
