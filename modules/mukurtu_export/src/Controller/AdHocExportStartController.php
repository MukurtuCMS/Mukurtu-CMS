<?php

namespace Drupal\mukurtu_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Traits\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Starts an ad-hoc export for a single node or a VBO bulk selection.
 */
class AdHocExportStartController extends ControllerBase {

  use ViewsBulkOperationsFormTrait;

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
    );
  }

  public function startNode(NodeInterface $node) {
    if (!\Drupal::currentUser()->hasPermission('access mukurtu export')) {
      throw new AccessDeniedHttpException();
    }

    $store = $this->tempStoreFactory->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', ['node' => [(int) $node->id() => (int) $node->id()]]);
    $store->set('exporter_id', 'csv');

    // Remove destination so Drupal's redirect subscriber doesn't send the user
    // back to the content list instead of the export settings page.
    \Drupal::request()->query->remove('destination');
    return $this->redirect('mukurtu_export.export_settings');
  }

  public function startMedia(MediaInterface $media) {
    if (!\Drupal::currentUser()->hasPermission('access mukurtu export')) {
      throw new AccessDeniedHttpException();
    }

    $store = $this->tempStoreFactory->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', ['media' => [(int) $media->id() => (int) $media->id()]]);
    $store->set('exporter_id', 'csv');

    \Drupal::request()->query->remove('destination');
    return $this->redirect('mukurtu_export.export_settings');
  }

  public function startBulk(string $view_id, string $display_id) {
    if (!\Drupal::currentUser()->hasPermission('access mukurtu export')) {
      throw new AccessDeniedHttpException();
    }

    $form_data = $this->getTempstoreData($view_id, $display_id);

    if (empty($form_data['list'])) {
      $this->messenger()->addWarning($this->t('No items selected for export.'));
      return $this->redirect('system.admin_content');
    }

    // Each VBO list item is [base_field_value, langcode, entity_type, entity_id, ...].
    $by_type = [];
    foreach ($form_data['list'] as $item) {
      $entity_type = $item[2];
      $entity_id = (int) $item[3];
      $by_type[$entity_type][$entity_id] = $entity_id;
    }

    $store = $this->tempStoreFactory->get('mukurtu_import');
    $store->delete('export_list_id');
    $store->set('ad_hoc_items', $by_type);
    $store->set('exporter_id', 'csv');

    $this->deleteTempstoreData($view_id, $display_id);

    \Drupal::request()->query->remove('destination');
    return $this->redirect('mukurtu_export.export_settings');
  }

}
