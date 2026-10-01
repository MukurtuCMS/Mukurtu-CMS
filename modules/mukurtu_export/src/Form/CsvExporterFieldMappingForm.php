<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\CsvExporterMappingSections;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edits the field mapping for one section of a CSV exporter.
 *
 * Mappings are split into sections so a single save stays well under PHP's
 * max_input_vars, which the full mapping for every bundle exceeds.
 */
class CsvExporterFieldMappingForm extends EntityForm {

  /**
   * The key of the section being edited.
   */
  protected string $sectionKey = '';

  public function __construct(protected CsvExporterMappingSections $mappingSections) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('mukurtu_export.csv_mapping_sections'));
  }

  /**
   * Title callback for the field mapping route.
   */
  public function title(CsvExporter $csv_exporter, string $section) {
    return $this->t('Field mapping: @section (@label)', [
      '@section' => $this->getSectionOrFail($csv_exporter, $section)['label'],
      '@label' => $csv_exporter->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $section
   *   The section key, from the route.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $section = '') {
    $this->sectionKey = $section;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;
    $section = $this->getSectionOrFail($entity, $this->sectionKey);

    $form['#tree'] = TRUE;
    $form['mapping'] = [];
    foreach ($section['items'] as $item) {
      $table = $this->buildBundleTable($entity, $item['type'], $item['bundle']);
      $table_key = "{$item['type']}__{$item['bundle']}";
      if ($item['wrap']) {
        $form['mapping'][$table_key] = [
          '#type' => 'details',
          '#open' => FALSE,
          '#title' => $item['label'],
          'fields' => $table,
        ];
      }
      else {
        $form['mapping'][$table_key] = ['fields' => $table];
      }
    }

    return $form;
  }

  /**
   * Builds the field mapping table for one bundle.
   */
  protected function buildBundleTable(CsvExporter $entity, string $type, string $bundle): array {
    $field_table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Export'),
        $this->t('Field name'),
        $this->t('Field label'),
        $this->t('CSV header label'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    foreach ($entity->getMappedFields($type, $bundle) as $weight => $mapped_field) {
      // Exclude 'behavior_settings' paragraph base field from the options.
      if ($type == 'paragraph' && $mapped_field['field_name'] == 'behavior_settings') {
        continue;
      }

      $row = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => 0,
      ];
      $row['export'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Export @field', ['@field' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $mapped_field['export'],
      ];
      $row['field_name'] = [
        '#type' => 'item',
        '#markup' => $mapped_field['field_name'],
      ];
      $row['field_label'] = [
        '#type' => 'item',
        '#markup' => $mapped_field['field_label'],
      ];
      $row['csv_header_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSV header label for @field', ['@field' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $mapped_field['csv_header_label'],
      ];
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $mapped_field['field_label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
      $field_table[$mapped_field['field_name']] = $row;
    }

    return $field_table;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save field mapping');
    unset($actions['delete']);
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    // Only this section's bundles are replaced; other sections are untouched.
    $field_list = $entity->get('entity_fields_export_list') ?? [];
    foreach ($form_state->getValue('mapping') ?? [] as $table_key => $table) {
      $rows = $table['fields'] ?? [];
      // Rows come back in build order, so apply the tabledrag weights.
      uasort($rows, fn($a, $b) => $a['weight'] <=> $b['weight']);
      $mapping = [];
      foreach ($rows as $field_name => $row) {
        if (!empty($row['export'])) {
          $mapping[$field_name] = $row['csv_header_label'];
        }
      }
      $field_list[$table_key] = $mapping;
    }
    $entity->set('entity_fields_export_list', $field_list);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = $this->entity->save();
    $section = $this->getSectionOrFail($this->entity, $this->sectionKey);
    $this->messenger()->addStatus($this->t('Saved the @section field mapping.', ['@section' => $section['label']]));
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    return $status;
  }

  /**
   * Returns a section, or throws a 404 if it doesn't exist.
   */
  protected function getSectionOrFail(CsvExporter $exporter, string $section_key): array {
    $section = $this->mappingSections->getSection($exporter, $section_key);
    if (!$section) {
      throw new NotFoundHttpException();
    }
    return $section;
  }

}
