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
    $files = $this->importer->getImportFiles();
    $form[] = $this->buildTable($files);


    // Submit for validation button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#button_type' => 'primary',
//      '#submit' => ['::submitFormValidateImport'],
/*       '#states' => [
        'visible' => [
          ':input[name="import_file[fids]"]' => ['filled' => TRUE],
        ],
      ], */
    ];
    return $form;
  }

  /**
   * Build the processor select control.
   *
   * @param array $options
   * @return array
   */
  private function processorSelect(array $options) {
    // For 2 or more options, allow user to select.
    if (count($options) > 1) {
      return [
        '#type' => 'select',
        '#options' => $options,
      ];
    }

    // For 1 (or 0?) disable the select so the user knows
    // they can't change it.
    return [
      '#type' => 'select',
      '#options' => $options,
      '#attributes' => ['disabled' => 'disabled'],
    ];
  }

  private function buildTable($files) {
    $table = [];

    if (empty($files)) {
      return $table;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $fileEntities = $storage->loadMultiple($files);

    // Build table.
    $table['import_files'] = [
      '#type' => 'table',
      '#caption' => $this->t('Files will be imported in order, top to bottom (lowest weight first).'),
      '#header' => [
        $this->t('Import File'),
        $this->t('Import Format'),
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

      // File names.
      $table['import_files'][$id]['label'] = [
        '#plain_text' => $entity->getFileName(),
      ];

      // Processor options.
      $options = $this->importer->getAvailableProcessors($entity);
      $table['import_files'][$id]['processor'] = $this->processorSelect($options);

      // Weights.
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
    //dpm($form_state->getValue('import_files'));
    //$form_state->setRedirect('mukurtu_import.import_upload_summary');
  }

}
