<?php

/**
 * @file
 * Contains \Drupal\mukurtu_roundtrip\Form\ImportFromFileForm.
 */

namespace Drupal\mukurtu_roundtrip\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ImportFromFileForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_from_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'mukurtu_roundtrip/tabulator';
    $form['#attached']['library'][] = 'mukurtu_roundtrip/import_table';

    // File upload widget.
    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Import File'),
      '#upload_location' => 'private://importfiles',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#attributes' => [
        'name' => 'import_file_upload',
      ],
    ];

    // Build the table showing the import file values.
    $import_file_contents = $this->getImportFileContents($form_state);
    if (!empty($import_file_contents)) {
      $headers = $import_file_contents[0];
      unset($import_file_contents[0]);

      $form['import_table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Table'),
        '#header' =>  $headers,
        '#states' => [
          'visible' => [
            ':input[name="import_file[fids]"]' => ['filled' => TRUE],
          ],
        ],
      ];

      foreach ($import_file_contents as $delta => $row) {
        foreach ($row as $key => $fieldvalue) {
          $form['import_table'][$delta][$headers[$key]] = [
            '#markup' => $fieldvalue,
          ];
        }
      }
    } else {
      // This is the first run (or the file didn't upload correctly).
      // Clear the session variable from any previous runs.
      $_SESSION['mukurtu_roundtrip'][$this->getFormId()] = [];
    }

    // Submit button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submitForValidation'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormValidateImport'],
      '#states' => [
        'visible' => [
          ':input[name="import_file[fids]"]' => ['filled' => TRUE],
        ],
      ],
    );

    $valid = $_SESSION['mukurtu_roundtrip'][$this->getFormId()]['valid'] ?? FALSE;
    if ($valid) {
      $form['actions']['submitForImport'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
        '#submit' => ['::submitFormImportAll'],
        '#states' => [
          'visible' => [
            ':input[name="import_file[fids]"]' => ['filled' => TRUE],
          ],
        ],
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Custom submission handler for import file validation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormValidateImport(array &$form, FormStateInterface $form_state) {
    $form_file = $form_state->getValue('import_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      //$file->setPermanent();
      $file->save();
    }

    if (!$file) {
      drupal_set_message("Error uploading file");
      return;
    }

    $data = $this->getImportFileContents($form_state);

    // Run the validation as a batch operation.
    $batch = [
      'title' => t('Import'),
      'operations' => [
        [
          'mukurtu_roundtrip_importbatch',
          [
            [
              'input' => $data,
              'save' => FALSE,
              'form_id' => $this->getFormId(),
            ],
          ],
        ],
      ],
      'finished' => 'mukurtu_roundtrip_import_complete_callback',
      'file' => drupal_get_path('module', 'mukurtu_roundtrip') . '/mukurtu_roundtrip.importbatch.inc',
    ];
    batch_set($batch);

    $form_state->setRebuild();
  }

  /**
   * Custom submission handler for importing all valid entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormImportAll(array &$form, FormStateInterface $form_state) {
    $form_file = $form_state->getValue('import_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      // When we add logging, we'll want the file used for import as permanent.
      $file->setPermanent();
      $file->save();
      $this->logImport($file->getOwnerId(), $file->fid->value);
    }

    if (!$file) {
      drupal_set_message("Error reading file");
      return;
    }

    $data = $this->getImportFileContents($form_state);

    // Run the import as a batch operation.
    $batch = [
      'title' => t('Import'),
      'operations' => [
        [
          'mukurtu_roundtrip_importbatch',
          [
            [
              'input' => $data,
              'save' => TRUE,
              'form_id' => $this->getFormId(),
            ],
          ],
        ],
      ],
      'finished' => 'mukurtu_roundtrip_import_complete_callback',
      'file' => drupal_get_path('module', 'mukurtu_roundtrip') . '/mukurtu_roundtrip.importbatch.inc',
    ];
    batch_set($batch);

    $form_state->setRebuild();
}

  protected function getImportFileContents(FormStateInterface $form_state) {
    $form_file = $form_state->getValue('import_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      if ($file) {
        $data = file_get_contents($file->getFileUri());
        $csv_array = array_map("str_getcsv", explode("\n", $data));
        //$csv_array = array_map("str_getcsv", file($file->getFileUri()));
        return $csv_array;
      }
    }

    return [];
  }

  /**
   * Log an import.
   *
   * This is quick and dirty just to keep track, long term
   * we should have a more fleshed out logging system.
   */
  protected function logImport($uid, $fid) {
    $time = time();

    $description = "";

    $connection = \Drupal::database();
    $result = $connection->insert('mukurtu_roundtrip_import_log')
      ->fields([
        'uid' => $uid,
        'fid' => $fid,
        'import_timestamp' => $time,
        'description' => $description,
      ])
      ->execute();
  }

}
