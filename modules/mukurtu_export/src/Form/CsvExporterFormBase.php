<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

class CsvExporterFormBase extends EntityForm
{
  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  public function __construct(EntityStorageInterface $entity_storage)
  {
    $this->entityStorage = $entity_storage;
  }

  public static function create(ContainerInterface $container)
  {
    $form = new static($container->get('entity_type.manager')->getStorage('csv_exporter'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this
        ->t('Machine name'),
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [
          $this,
          'exists',
        ],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->getDescription(),
    ];

    $form['include_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include files in export package'),
      '#default_value' => $entity->getIncludeFiles(),
    ];

    return $form;
  }

  public function exists($entity_id, array $element, FormStateInterface $form_state)
  {
    $query = $this->entityStorage->getQuery();

    $result = $query
      ->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    return (bool) $result;
  }

  protected function actions(array $form, FormStateInterface $form_state)
  {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }


  public function save(array $form, FormStateInterface $form_state)
  {
    $entity = $this->getEntity();
    $status = $entity->save();
    $form_state->setRedirect('mukurtu_export.export_settings');
  }
}
