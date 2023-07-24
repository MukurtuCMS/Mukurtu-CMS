<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\Form\ExportBaseForm;
use Drupal\Core\Url;

/**
 * Export Item Summary and Format Selection form.
 */
class ExportItemAndFormatSelectionForm extends ExportBaseForm {

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
    // Assume export list is empty until we come across an item.
    $exportListIsEmpty = TRUE;
    foreach ($this->source->getEntities() as $entityType => $entities) {
      if (!empty($entities)) {
        $exportListIsEmpty = FALSE;
        break;
      }
    }

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

    // Only show this if export list is not empty
    if (!$exportListIsEmpty) {
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
    }
    else {
      // Alternate form display if export list is empty.
      $form['message'] = [
        '#type' => 'processed_text',
        '#text' => $this->t('You currently have no items marked for export. You can visit <a href=":manageContent">Manage Content</a> and <a href=":browseContent">Browse Content</a> to add items for export.',
          [
            ':manageContent' => url::fromRoute("view.mukurtu_manage_all_content.mukurtu_manage_content")->toString(),
            ':browseContent' => url::fromRoute("mukurtu_browse.browse_page")->toString(),
          ]),
        '#format' => 'full_html'
      ];
    }

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
