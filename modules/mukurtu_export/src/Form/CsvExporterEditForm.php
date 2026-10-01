<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edits a CSV exporter's settings and links to its field mapping sections.
 */
class CsvExporterEditForm extends CsvExporterFormBase {

  /**
   * The CSV exporter field mapping sections.
   *
   * @var \Drupal\mukurtu_export\CsvExporterMappingSections
   */
  protected $mappingSections;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->mappingSections = $container->get('mukurtu_export.csv_mapping_sections');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $form['field_mapping'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Field mapping'),
      '#description' => $this->t('Choose which fields each section exports and set their CSV header labels.'),
    ];
    $form['field_mapping']['sections'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Section'),
        $this->t('Exported fields'),
        $this->t('Operations'),
      ],
    ];

    foreach ($this->mappingSections->getSections($entity) as $section_key => $section) {
      $exported = 0;
      foreach ($section['items'] as $item) {
        $exported += count($entity->getExportFields($item['type'], $item['bundle']));
      }
      $url = $entity->toUrl('field-mapping-form');
      $url->setRouteParameter('section', $section_key);
      $form['field_mapping']['sections'][$section_key] = [
        'label' => ['#plain_text' => $section['label']],
        'exported' => ['#plain_text' => $exported],
        'operations' => Link::fromTextAndUrl($this->t('Edit @section field mapping', ['@section' => $section['label']]), $url)->toRenderable(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update');
    return $actions;
  }

}
