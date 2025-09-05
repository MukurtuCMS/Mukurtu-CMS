<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to manage the site level local contexts projects directory.
 */
class ManageProjectsDirectorySite extends ManageProjectsDirectoryBase {

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
    $form_state->setTemporaryValue('config', $this->configFactory()->getEditable('mukurtu_local_contexts.settings'));
    return parent::buildForm($form, $form_state);
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
    $config->set('mukurtu_local_contexts_manage_site_projects_directory_description', $description)->save();
  }

}
