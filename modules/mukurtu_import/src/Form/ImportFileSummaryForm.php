<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Provides a Mukurtu Import form.
 */
class ImportFileSummaryForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_import_file_summary';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $metadataFiles = $this->getMetadataFiles();

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        // /'',
        $this->t('File'),
        //$this->t('Type'),
        $this->t('Mapping'),
        '',
      ],
      '#attributes' => [
        'id' => 'my-module-table'
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'draggable-weight',
      ]],
    ];

    foreach ($metadataFiles as $fid) {
      $metadataFile = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$metadataFile) {
        continue;
      }
  /*
        $form['table'][$fid] = [
          'data' => [],
        ]; */
      //$form['table'][$fid]['#attributes']['class'] = ['draggable'];
/*       $form['table'][$fid]['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => 0,
        '#attributes' => [
          'class' => [
            'draggable-weight'
          ]
        ],
      ]; */
      $form['table'][$fid]['label'] = [
        '#markup' => $metadataFile->label(),
      ];

      /*       $form['table'][$fid]['entity_type_id'] = [
        '#type' => 'select',
        '#options' => [
          'unknown' => $this->t('Select Type'),
          'content' => $this->t('Content'),
          'media' => $this->t('Media'),
          'community' => $this->t('Community'),
          'protocol' => $this->t('Cultural Protocol'),
        ],
        '#ajax' => [
          'callback' => [$this, 'fileTypeAjaxCallback'],
          'event' => 'change',
        ],
      ]; */

      $mappedFieldMsg = $this->getMappedFieldsMessage($fid);
      $form['table'][$fid]['mapping'] = [
/*         '#attributes' => [
          'id' => "mapping-config-$fid",
        ], */
        '#type' => 'select',
        '#options' => $this->getImportConfigOptions($fid),
        '#default_value' => 'custom',
        '#ajax' => [
          'callback' => [$this, 'mappingChangeAjaxCallback'],
          'event' => 'change',
        ],
        '#suffix' => "<div id=\"mapping-summary-{$fid}\">{$mappedFieldMsg}</div>",
        /* '#type' => 'submit',
        '#value' => $this->t('Define Custom Mapping'),
        '#button_type' => 'primary',
        '#submit' => ['::defineCustomMapping'], */
      ];

      $form['table'][$fid]['edit'] = [
        '#type' => 'submit',
        '#name' => "edit-{$fid}",
        '#value' => $this->t('Customize Settings'),
        '#button_type' => 'primary',
        '#submit' => ['::defineCustomMapping'],
      ];
    }

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
      '#value' => $this->t('Next'),
    ];

    return $form;
  }

  protected function getImportConfigOptions($fid) {
    return ['custom' => 'Custom Mapping'];
  }

  protected function getMappedFieldsMessage($fid) {
    // Get the import config for this file.
    $importConfig = $this->getImportConfig($fid);

    // Get the field mapping from the config.
    $process = $importConfig->getMapping(); //$this->getFileProcess($fid);

    // Load the file and read the headers.
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $fileHeaders = $this->getCSVHeaders($file);

    // Compare the import config to the headers.
    $mappingHeaders = array_column($process, 'source');
    $diff = array_diff($fileHeaders, $mappingHeaders);
    $mappedCount = count($fileHeaders) - count($diff);
    $targets = array_column($process, 'target');
    $targetCounts = array_count_values($targets);
    $ignored = $targetCounts[-1] ?? 0;

    if ($ignored) {
      return $this->t("@num of @total fields mapped, @ignored ignored", [
        '@num' => $mappedCount,
        '@total' => count($fileHeaders),
        '@ignored' => $ignored,
      ]);
    }

    return $this->t("@num of @total import fields mapped", [
      '@num' => $mappedCount,
      '@total' => count($fileHeaders),
    ]);
  }

  public function mappingChangeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;

    // User changed the process, save this change.
    //$process = $this->getFileProcess($fid);
    //$this->setFileProcess($fid, $process);

    // @todo Use setImportConfig here once we've got everything in place.

    // Update the field mapping message.
    $response = new AjaxResponse();

    // Check how many fields for this file we have mapped with the selected process.
    $msg = $this->getMappedFieldsMessage($fid);
    $response->addCommand(new ReplaceCommand("#mapping-summary-{$fid}", "<div id=\"mapping-summary-{$fid}\">{$msg}</div>"));
    //$response->addCommand(new ReplaceCommand("#mukurtu-import-import-file-summary .form-item-table-{$fid}-mapping", "<span>$fid:$entity_type_id</span>"));
    return $response;
  }

  public function fileTypeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;
    if ($fid) {
      $settings = $form_state->getValue('table');
      $entity_type_id = $settings[$fid]['entity_type_id'] ?? NULL;
    }

    $form['table'][$fid]['mapping'] = [
      '#attributes' => [
        'id' => "mapping-config-$fid",
      ],
      '#type' => 'select',
      '#options' => ['custom' => "Custom Mapping ($entity_type_id)", 'existing' => 'Existing Test'],
      '#default_value' => 'custom',
      /* '#type' => 'submit',
        '#value' => $this->t('Define Custom Mapping'),
        '#button_type' => 'primary',
        '#submit' => ['::defineCustomMapping'], */
    ];

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#mapping-config-{$fid}", $form['table'][$fid]['mapping']));
    //$response->addCommand(new ReplaceCommand("#mukurtu-import-import-file-summary .form-item-table-{$fid}-mapping", "<span>$fid:$entity_type_id</span>"));
    return $response;
  }

  protected function getMappingOptions($entity_type_id) {

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  public function defineCustomMapping(array &$form, FormStateInterface $form_state) {
    // Get the file ID.
    $element = $form_state->getTriggeringElement();
    $fid = $element['#parents'][1] ?? NULL;
    if ($fid && is_numeric($fid)) {
      $form_state->setRedirect('mukurtu_import.custom_strategy_from_file_form', ['file' => $fid]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

}
