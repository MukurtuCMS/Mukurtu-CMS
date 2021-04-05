<?php

namespace Drupal\mukurtu_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\PluginBase;

class MukurtuBrowseCollectionsController extends ControllerBase {

  public function content() {
    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_browse_collections',
      '#display_id' => 'browse_collections_block',
      '#embed' => TRUE,
    ];

    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => 'search_api:views_block__mukurtu_browse_collections__browse_collections_block']);

    // Render the facet block for each of them.
    $facets = [];
    if ($facetEntities) {
      $block_manager = \Drupal::service('plugin.manager.block');
      foreach ($facetEntities as $facet_id => $facetEntity) {
        $config = [];
        $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
        if ($block_plugin) {
          $access_result = $block_plugin->access(\Drupal::currentUser());
          if ($access_result) {
            $facets[$facet_id] = $block_plugin->build();
          }
        }
      }
    }

    return [
      '#theme' => 'mukurtu_collection_browse',
      '#results' => $browse_view_block,
      '#facets' => $facets,
    ];
  }
}
