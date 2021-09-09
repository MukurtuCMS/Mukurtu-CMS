<?php

namespace Drupal\mukurtu_roundtrip\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class MukurtuImportReportForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_report_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mid = NULL) {
    $message = \Drupal::service('entity_type.manager')->getStorage('message')->load($mid);

    if (!$message) {
      return $form;
    }

    // Display the message.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder($message->getEntityTypeId());
    $form['report'] = $view_builder->view($message);

    // Return to dashboard button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Dashboard'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_core.dashboard');
  }
}
