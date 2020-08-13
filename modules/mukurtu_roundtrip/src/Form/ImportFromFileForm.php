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
      ];

      foreach ($import_file_contents as $delta => $row) {
        foreach ($row as $key => $fieldvalue) {
          $form['import_table'][$delta][$headers[$key]] = [
            '#markup' => $fieldvalue,
          ];
        }
      }

      // var table = new Tabulator("#edit-import-table", {});
    }

    // Submit button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submitForValidation'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormValidateImport'],
    );

    $valid = $form_state->getValue('valid_import') ?? FALSE;
    if ($valid) {
      $form['actions']['submitForImport'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
        '#submit' => ['::submitFormImportAll'],
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

    $data = file_get_contents($file->getFileUri());

    // Run the import file through the deserializer.
    $serializer = \Drupal::service('serializer');
    $entities = $serializer->deserialize($data, 'Drupal\node\Entity\Node', 'csv', []);
    $valid = 0;

    // Validate the resultant entities.
    foreach ($entities as $entity) {
      $violations = $entity->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          drupal_set_message($violation->getMessage());
        }
      } else {
        $valid++;
      }
    }

    // If all entities are valid, enable the import button.
    if ($valid == count($entities)) {
      $form_state->setValue('valid_import', TRUE);
    }

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
    }

    if (!$file) {
      drupal_set_message("Error reading file");
      return;
    }
    $data = file_get_contents($file->getFileUri());

    $serializer = \Drupal::service('serializer');
    $entities = $serializer->deserialize($data, 'Drupal\node\Entity\Node', 'csv', []);
    $valid = 0;

    foreach ($entities as $entity) {
      $violations = $entity->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          drupal_set_message($violation->getMessage());
        }
      } else {
        $valid++;
        //$entity->save();
      }
    }

    drupal_set_message("Imported $valid items.");
    //$form_state->setRebuild();
  }

  protected function getImportFileContents(FormStateInterface $form_state) {
    $form_file = $form_state->getValue('import_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      if ($file) {
        $data = file_get_contents($file->getFileUri());
        $csv_array = array_map("str_getcsv", explode("\n", $data));
        return $csv_array;
      }
    }

    return [];
  }

}
