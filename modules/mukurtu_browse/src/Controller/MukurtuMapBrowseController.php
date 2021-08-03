<?php

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

class MukurtuMapBrowseController extends ControllerBase {

  public function content() {
    // Render the map browse view block.
    $map_browse_view_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_browse_map',
      '#display_id' => 'mukurtu_browse_map_block',
      '#embed' => TRUE,
    ];

    $teasers['mukurtu_map_browse_teasers'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'mukurtu-map-browse-teasers',
      ],
    ];

    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => 'search_api:views_block__mukurtu_browse_map__mukurtu_browse_map_block']);

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
      '#theme' => 'mukurtu_map_browse',
      '#teasers' => $teasers,
      '#map' => $map_browse_view_block,
      '#facets' => $facets,
      '#attached' => [
        'library' => [
          'leaflet/leaflet',
          'mukurtu_browse/mukurtu-leaflet-markercluster',
          'mukurtu_browse/mukurtu-leaflet-custom-markercluster',
//          'mukurtu_browse/mukurtu-leaflet-preview',
        ],
      ],
    ];
  }

  public function getTeasersAjax($nodes) {
    $response = new AjaxResponse();

    $content['mukurtu_map_browse_teasers'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'mukurtu-map-browse-teasers',
      ],
    ];

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

    foreach ($nodes as $node) {
      $pre_render = $view_builder->view($node, 'teaser');
      $content['mukurtu_map_browse_teasers'][] = $pre_render;
    }

    $response->addCommand(new ReplaceCommand('#mukurtu-map-browse-teasers', $content));

    return $response;
  }

}
