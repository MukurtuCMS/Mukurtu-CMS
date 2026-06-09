<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface;
use Drupal\views_bulk_operations\Traits\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the ad-hoc export bulk action.
 *
 * VBO redirects here (via confirm_form_route_name on AdHocExportAction) before
 * executing the action. Reads selected entities from the VBO tempstore, stores
 * them as ad_hoc_items in the export tempstore, then sends the user directly
 * to /admin/export/settings to configure and run the export.
 */
class AdHocExportItemsForm extends FormBase {

  use ViewsBulkOperationsFormTrait;

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly ViewsBulkOperationsActionProcessorInterface $actionProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('views_bulk_operations.processor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_export_adhoc_export_items';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $view_id = NULL, ?string $display_id = NULL): array {
    $form_data = $this->getFormData($view_id, $display_id);

    if (!\array_key_exists('action_id', $form_data)) {
      $this->messenger()->addWarning($this->t('No items selected for export.'));
      $form_state->setRedirect('system.admin_content');
      return $form;
    }

    $form_state->set('views_bulk_operations', $form_data);

    $form['list'] = $this->getListRenderable($form_data);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Selected'),
      '#button_type' => 'primary',
    ];
    $this->addCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_data = $form_state->get('views_bulk_operations');

    // Parse selected entity IDs from VBO bulk form keys.
    // Each list item is [base_field_value, langcode, entity_type_id, entity_id].
    $by_type = [];
    foreach ($form_data['list'] as $item) {
      $entity_type = $item[2];
      $entity_id = (int) $item[3];
      $by_type[$entity_type][$entity_id] = $entity_id;
    }

    // Store items in the export tempstore and clear any active list.
    $store = $this->tempStoreFactory->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', $by_type);
    $store->set('exporter_id', 'csv');

    // Clear the VBO tempstore.
    $this->deleteTempstoreData($form_data['view_id'], $form_data['display_id']);

    $form_state->setRedirect('mukurtu_export.export_settings');
  }

}
