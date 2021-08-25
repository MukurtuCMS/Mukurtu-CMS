<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportEntityValidationDetailsForm extends MukurtuImportFormBase {
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_entity_validation_details_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL, $index = NULL) {
    $form = parent::buildForm($form, $form_state);

    dpm("$fid => $index");
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
