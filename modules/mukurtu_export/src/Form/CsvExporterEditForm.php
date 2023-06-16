<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;

class CsvExporterEditForm extends CsvExporterFormBase {
  protected function actions(array $form, FormStateInterface $form_state)
  {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update');
    return $actions;
  }

}
