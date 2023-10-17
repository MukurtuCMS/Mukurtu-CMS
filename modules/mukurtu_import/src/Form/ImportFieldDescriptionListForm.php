<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;

class ImportFieldDescriptionListForm extends ImportBaseForm {

  public function getFormId() {
    return 'mukurtu_import_format_by_bundle';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle = NULL) {
    /** @var \Drupal\mukurtu_import\MukurtuImportFieldProcessPluginManager $manager */
    $importFieldManager = \Drupal::service('plugin.manager.mukurtu_import_field_process');

    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
    $options = [];


    $import_field_options = $this->buildTargetOptions($entity_type, $bundle);
    unset($import_field_options[-1]);

    foreach ($import_field_options as $field_target => $target_label) {
      $field_components = explode('/', $field_target);
      $field_name = $field_components[0];
      $field_property = $field_components[1] ?? NULL;
      $processPlugin = $importFieldManager->getInstance(['field_definition' => $fields[$field_name]]);
      $options[$field_target] = [
        'label' => $target_label,
        'description' => $fields[$field_name]->getDescription() ?? '',
        'format' => $processPlugin->getFormatDescription($fields[$field_name], $field_property),
      ];
    }

    // Define tableselect.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'label' => $this->t('Field'),
        'description' => $this->t('Field Description'),
        'format' => $this->t('Import Format Description'),
      ],
      '#options' => $options,
      '#empty' => $this->t('No fields found'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download CSV Template'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_fields = array_filter($form_state->getValue('table'));

    // Gather the selected field labels.
    $headers = [];
    foreach ($selected_fields as $field_name) {
      $headers[] = $form['table']['#options'][$field_name]['label'];
    }

    // Convert to CSV format.
    $handle = fopen('php://memory', 'r+');
    fputcsv($handle, $headers);
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    // Trigger CSV download.
    $response = new \Symfony\Component\HttpFoundation\Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="fields.csv"');

    $form_state->setResponse($response);
  }

}
