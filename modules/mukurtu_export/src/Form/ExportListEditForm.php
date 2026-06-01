<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for editing an existing Export List.
 */
class ExportListEditForm extends ExportListFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Update');
    return $form;
  }

}
