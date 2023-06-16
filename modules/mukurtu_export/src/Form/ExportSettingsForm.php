<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\Form\ExportBaseForm;

/**
 * Export Plugin Configuration Form.
 */
class ExportSettingsForm extends ExportBaseForm
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_export_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form += $this->exporter->settingsForm($form, $form_state);

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitBack'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Export'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function submitBack(array &$form, FormStateInterface $form_state)
  {
    $form_state->setRedirect('mukurtu_export.export_item_and_format_selection');
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $settings = $this->exporter->getSettings($form, $form_state);
    $this->exporter->setConfiguration(['settings' => $settings]);
    $this->setExporterConfig($this->exporter->getConfiguration());

    $this->executable->export();

    $form_state->setRedirect('mukurtu_export.export_results');
  }
}
