<?php

namespace Drupal\mukurtu_migrate\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Mukurtu migrate results form.
 *
 * @internal
 */
class ResultsForm extends MukurtuMigrateFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_migrate_results_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Migration Results');

    $success_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_migrate_results',
      '#display_id' => 'success_block',
      '#embed' => TRUE,
    ];
    $form['success'] = $success_block;

    $failure_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_migrate_results',
      '#display_id' => 'failure_block',
      '#embed' => TRUE,
    ];
    $form['failure'] = $failure_block;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_core.dashboard');
  }

}
