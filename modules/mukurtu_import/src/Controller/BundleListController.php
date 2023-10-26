<?php

namespace Drupal\mukurtu_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class BundleListController extends ControllerBase {

  public function bundlesList() {
    $build = [];
    $entity_types = ['node', 'media', 'paragraph', 'file'];
    $entity_type_labels = [];

    foreach ($entity_types as $entity_type) {
      $entity_type_labels[$entity_type] = $this->entityTypeManager()->getDefinition($entity_type)->getLabel();
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
      foreach ($bundles as $bundle_id => $bundle_info) {
        $url = Url::fromRoute('mukurtu_import.fields_list', ['entity_type' => $entity_type, 'bundle' => $bundle_id]);
        $build[$entity_type][] = Link::fromTextAndUrl($bundle_info['label'], $url)->toRenderable();
      }
    }

    return [
      '#theme' => 'mukurtu_import_entity_bundle_list',
      '#entity_types' => $entity_types,
      '#entity_type_labels' => $entity_type_labels,
      '#entity_type_bundle_links' => $build,
    ];
  }

  public function getFieldListTitle($entity_type, $bundle) {
    $entity_type_label = $this->entityTypeManager()->getDefinition($entity_type)->getLabel();
    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
    $bundle_label = isset($bundle_info[$bundle]) ? $bundle_info[$bundle]['label'] : '';
    return $this->t('Import Format Description for @entity_type: @bundle', ['@entity_type' => $entity_type_label, '@bundle' => $bundle_label]);
  }

}
