<?php

namespace Drupal\mukurtu_roundtrip\Form\MultiStepImport;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;

class MukurtuImportEntityValidationDetailsForm extends MukurtuImportFormBase {
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'mukurtu_import_entity_validation_details_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL, $index = NULL) {
    $form = parent::buildForm($form, $form_state);
    $report = $this->importer->getValidationReport();

    if (isset($report[$fid]['invalid'][$index]['entity'])) {
      $entity = $report[$fid]['invalid'][$index]['entity'];

      if (method_exists($entity, 'getPreviewMode')) {
        $editForm = \Drupal::service('entity.manager')
          ->getFormObject('node', 'default')
          ->setEntity($entity);
        $form['edit_form'] = \Drupal::formBuilder()->getForm($editForm);
      } else {
        $violations = $report[$fid]['invalid'][$index]['violations'];

        $file = $this->fileStorage->load($fid);
        $filename = $file->getFileName();
        $caption = $entity->getTitle() ? $this->t('Validation Errors for item %title in file %filename', ['%title' => $entity->getTitle(), '%filename' => $filename]) : $this->t('Validation Errors');

        // Build table.
        $table['violations'] = [
          '#type' => 'table',
          '#caption' => $caption,
          '#header' => [
            $this->t('Field'),
            $this->t('Message'),
          ],
          '#empty' => $this->t('No errors.'),
        ];

        foreach ($violations as $delta => $violation) {
/*           dpm($violation->getParameters());
          dpm($violation->getMessageTemplate());
          dpm($violation->getMessage());
          dpm($violation->getPropertyPath()); */
          $field = $violation->getPropertyPath();
          $message = $violation->getMessage();
          $table['violations'][$delta]['field'] = ['#plain_text' => $field];
          $table['violations'][$delta]['message'] = ['#plain_text' => $message->render()];
        }

        $form['errors'] = $table;
      }

     // $test = \Drupal::service('entity.form_builder')->getForm($entity, 'edit');
      //dpm($test);
    }

    // Back button.
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormBack'],
    ];

    // Back button.
    $form['actions']['backToFileList'] = [
      '#type' => 'submit',
      '#value' => $this->t('Alter import files'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormFileList'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitFormBack(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_roundtrip.import_validation_complete');
  }

  public function submitFormFileList(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_roundtrip.import_start');
  }

}
