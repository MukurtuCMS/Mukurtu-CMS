<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;

class MukurtuImportUploadSummaryForm extends MukurtuImportFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_upload_summary_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // These are the files to import.
    $files = $this->importer->getInputFiles();
    $form[] = $this->buildTable($files);


    // Submit for validation button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#button_type' => 'primary',
//      '#submit' => ['::submitFormValidateImport'],
/*       '#states' => [
        'visible' => [
          ':input[name="import_file[fids]"]' => ['filled' => TRUE],
        ],
      ], */
    );
    return $form;
  }

  private function buildTable($files) {
    $table = [];

    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $fileEntities = $storage->loadMultiple($files);

    // Build table.
    $table['import_files'] = [
      '#type' => 'table',
      '#caption' => $this->t('Import Files'),
      '#header' => [
        $this->t('Import File'),
        $this->t('Import Processor'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No import files provided.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'group-order-weight',
        ],
      ],
    ];

    // Build rows.
    $weight = 0;
    foreach ($fileEntities as $id => $entity) {
      $table['import_files'][$id]['#attributes']['class'][] = 'draggable';
      $table['import_files'][$id]['#weight'] = $weight;

      // Label col.
      $table['import_files'][$id]['label'] = [
        '#plain_text' => $entity->getFileName(),
      ];

      // ID col.
      $table['import_files'][$id]['id'] = [
        '#plain_text' => $entity->id(),
      ];

      // Weight col.
      $table['import_files'][$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $entity->getFileName()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight++,
        '#attributes' => ['class' => ['group-order-weight']],
      ];
    }

    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm($form_state->getValue('import_files'));
    //$form_state->setRedirect('mukurtu_import.import_upload_summary');
  }

}
