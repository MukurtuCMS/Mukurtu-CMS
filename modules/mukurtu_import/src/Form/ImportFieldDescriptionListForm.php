<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;

class ImportFieldDescriptionListForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_format_by_bundle';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle = NULL): array {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $options = [];


    $import_field_options = $this->buildTargetOptions($entity_type, $bundle);
    unset($import_field_options[-1]);

    foreach ($import_field_options as $field_target => $target_label) {
      $field_components = explode('/', $field_target);
      $field_name = $field_components[0];
      $field_property = $field_components[1] ?? NULL;
      $process_plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $fields[$field_name]]);
      $options[$field_target] = [
        'label' => $target_label,
        'description' => $fields[$field_name]->getDescription() ?? '',
        'format' => $process_plugin->getFormatDescription($fields[$field_name], $field_property),
      ];
    }

    $form['entity_type_id'] = [
      '#type' => 'hidden',
      '#value' => $entity_type,
    ];
    $form['bundle'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];

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

    // Form actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download CSV Template'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_type_id = $form_state->getValue('entity_type_id');
    $bundle = $form_state->getValue('bundle');

    $entity_type_label = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
    $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
    $bundle_label = $bundle && isset($bundle_info[$bundle]) ? $bundle_info[$bundle]['label'] : '';
    $filename = $bundle && $bundle != $entity_type_id ? "{$entity_type_label} - {$bundle_label}.csv" : "{$entity_type_label}.csv";
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
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    $form_state->setResponse($response);
  }

}
