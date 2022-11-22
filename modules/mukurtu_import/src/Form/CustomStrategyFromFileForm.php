<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\mukurtu_import\Form\ImportBaseForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\MigrateExecutable;
use League\Csv\Reader;
use Exception;
use Drupal\file\FileInterface;

/**
 * Provides a Mukurtu Import form.
 */
class CustomStrategyFromFileForm extends ImportBaseForm {
  protected $fieldDefinitions;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_import_custom_strategy_from_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FileInterface $file = NULL) {
    $entity_type_id = 'node';
    $bundle = 'digital_heritage';

    if(!$file) {
      return $form;
    }

    $headers = $this->getCSVHeaders($file);
    if (empty($headers)) {
      return $form;
    }

    $form['fid'] = [
      '#type' => 'value',
      '#value' => $file->id(),
    ];

    $form['mappings'] = [
      '#type' => 'table',
      '#caption' => $this->t('Define custom source/target mappings for file %file', ['%file' => $file->getFilename()]),
      '#header' => [
        $this->t('Source'),
        $this->t('Target Field'),
        '',
      ],
    ];

    foreach ($headers as $delta => $header) {
      $form['mappings'][$delta]['source_title'] = [
        '#plain_text' => $header,
      ];
      $form['mappings'][$delta]['target'] = $this->buildTargetOptions($header, $entity_type_id, $bundle);
      $form['mappings'][$delta]['source'] = [
        '#type' => 'value',
        '#value' => $header,
      ];

    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#button_type' => 'primary',
      '#submit' => ['::submitCancel'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    // Skip validation if we're cancelling.
    if ($element['#parents'][0] == 'cancel') {
      return;
    }

    $mappings = $form_state->getValue('mappings');

    // Check for duplicate target mapping.
    $targets = array_column($mappings, 'target');
    $uniqueTargets = array_unique($targets);
    if (count($uniqueTargets) != count($targets)) {

      foreach (array_count_values($targets) as $dupeTarget => $count) {
        // Ignore the ignore field option, users can have duplicates of that.
        if ($count < 2 || $dupeTarget == -1) {
          continue;
        }

        foreach ($targets as $delta => $target) {
          if ($dupeTarget == $target) {
            $form_state->setError($form['mappings'][$delta], $this->t("Only a single source can be mapped to each target field."));
          }
        }
      }
    }
  }

  public function submitCancel(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save mappings.
    $fid = $form_state->getValue('fid');
    $this->setFileProcess($fid, $form_state->getValue('mappings'));

    // Go back to the file summary form.
    $form_state->setRedirect('mukurtu_import.import_files');
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The field definitions.
   */
  protected function getFieldDefinitions($entity_type_id, $bundle = NULL) {
    // Memoize the field defs.
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $this->fieldDefinitions[$entity_type_id][$bundle] = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    }
    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

  /**
   * Compare field labels against a search string.
   *
   * @param string $needle
   *   The search term.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return string|null
   *   The field name of the match or NULL if no matches found.
   */
  protected function searchFieldLabels($needle, $entity_type_id, $bundle = NULL) {
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fieldDefs as $field_name => $field) {
      if ($needle == mb_strtolower($field->getLabel())) {
        return $field_name;
      }
    }
    return NULL;
  }

  /**
   * Build the mapper target options for a single source column.
   *
   * @param string $source
   *   The source column value.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The select form element.
   */
  protected function buildTargetOptions($source, $entity_type_id, $bundle = NULL) {
    $options = [-1 => $this->t('Ignore - Do not import')];
    foreach ($this->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      $options[$field_name] = $field_definition->getLabel();
    }

    return [
      '#type' => 'select',
      '#default_value' => $this->getAutoMappedTarget($source, $entity_type_id, $bundle),
      '#options' => $options,
    ];
  }

  /**
   * Some basic logic to try and auto-map source to target.
   *
   * 1. Check for field name matches (case insensitive).
   * 2. Check for full field label matches (case insensitive).
   */
  protected function getAutoMappedTarget($source, $entity_type_id, $bundle = NULL) {
    $needle = mb_strtolower($source);
    $fieldDefs = $this->getFieldDefinitions($entity_type_id, $bundle);

    // Check if we have a (case insensitive) field name match.
    if (isset($fieldDefs[$needle])) {
      return $needle;
    }

    // Check for field label matches.
    if ($fieldLabelMatch = $this->searchFieldLabels($needle, $entity_type_id, $bundle)) {
      return $fieldLabelMatch;
    }

    return -1;
  }

  /**
   * Get the CSV headers from a file.
   */
  protected function getCSVHeaders(FileInterface $file) {
    try {
      $csv = Reader::createFromPath($file->getFileUri(), 'r');
    } catch (Exception $e) {
      return [];
    }
    $csv->setHeaderOffset(0);
    return $csv->getHeader();
  }

}
