<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Adds a CSV exporter, starting it with the default field mapping.
 */
class CsvExporterAddForm extends CsvExporterFormBase {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save and map fields');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    // Field mappings are edited from the edit form, so go there next.
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    return $status;
  }

}
