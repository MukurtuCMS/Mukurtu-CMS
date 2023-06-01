<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\Form\ExportBaseForm;

/**
 * Export Item Summary and Format Selection form.
 */
class ExportItemAndFormatSelection extends ExportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_item_and_format_selection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['export_list']['content'] = [
      '#type' => 'view',
      '#name' => 'export_list_content',
      '#display_id' => 'export_content_list_block',
      '#embed' => TRUE,
    ];

    $form['export_list']['media'] = [
      '#type' => 'view',
      '#name' => 'export_list_media',
      '#display_id' => 'export_media_list_block',
      '#embed' => TRUE,
    ];

    $form['export_list']['exporter'] = [
      '#type' => 'select',
      '#title' => 'Select Export Format',
      '#default_value' => $this->getExporterId(),
      '#options' => $this->getExporterOptions(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next - Configure Export'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->setExporterId($form_state->getValue('exporter'));

    $form_state->setRedirect('mukurtu_export.export_settings');
  }


}
