<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Vocabulary picker form that initiates a taxonomy term CSV export.
 *
 * Selected vocabulary terms are stored as ad_hoc_items so the existing
 * ExportSettingsForm and BatchExportExecutable handle the rest unchanged.
 */
class TaxonomyExportStartForm extends FormBase {

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'mukurtu_taxonomy_export_start';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    $options = [];
    foreach ($vocabularies as $vocab) {
      $options[$vocab->id()] = $vocab->label();
    }
    asort($options);

    $form['vocabularies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Taxonomies'),
      '#description' => $this->t('Select one or more taxonomies to export. All terms in the selected taxonomies will be included.'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Configure Export'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected = array_filter($form_state->getValue('vocabularies'));

    $ids = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', array_values($selected), 'IN')
      ->accessCheck(TRUE)
      ->execute();

    if (empty($ids)) {
      $this->messenger()->addWarning($this->t('No terms found in the selected taxonomies.'));
      return;
    }

    $ids = array_map('intval', array_values($ids));

    $store = $this->tempStoreFactory->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', ['taxonomy_term' => array_combine($ids, $ids)]);
    $store->set('exporter_id', 'csv');

    $form_state->setRedirect('mukurtu_export.export_settings');
  }

}
